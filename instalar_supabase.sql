-- MATHEUS PET CARE / PATAAGENDA V3
-- Execute UMA VEZ em projeto Supabase NOVO: SQL Editor > New query > Run.
-- Cria cadastros reais, pets, agenda, regras de acesso e publicação realtime.
-- Os dados de outros usuários NUNCA ficam públicos: apenas estados anônimos dos horários.

create extension if not exists pgcrypto;
create schema if not exists private;
revoke all on schema private from public;
grant usage on schema private to authenticated;

create table public.app_admins (
  user_id uuid primary key references auth.users(id) on delete cascade,
  added_at timestamptz not null default now()
);

create table public.profiles (
  id uuid primary key references auth.users(id) on delete cascade,
  email varchar(180) not null default '',
  full_name varchar(120) not null default '',
  phone varchar(24) not null default '',
  street varchar(140) not null default '',
  house_number varchar(20) not null default '',
  neighborhood varchar(80) not null default '',
  city varchar(90) not null default '',
  reference varchar(150) not null default '',
  notes varchar(800) not null default '',
  created_at timestamptz not null default now()
);

create table public.client_tags (
  user_id uuid primary key references public.profiles(id) on delete cascade,
  favorite boolean not null default false,
  monthly_frequency int not null default 0 check (monthly_frequency between 0 and 31),
  admin_notes varchar(1500) not null default ''
);

-- Clientes antigos ou atendimentos cadastrados MANUALMENTE pelo Matheus.
-- Não dão acesso ao login; um cliente deve criar sua conta para agendar pela internet.
create table public.manual_clients (
  id uuid primary key default gen_random_uuid(),
  full_name varchar(120) not null check (length(trim(full_name)) >= 1),
  phone varchar(24) not null default '',
  street varchar(140) not null default '',
  house_number varchar(20) not null default '',
  neighborhood varchar(80) not null default '',
  city varchar(90) not null default '',
  reference varchar(150) not null default '',
  notes varchar(800) not null default '',
  favorite boolean not null default false,
  monthly_frequency int not null default 0 check (monthly_frequency between 0 and 31),
  created_at timestamptz not null default now()
);

create table public.pets (
  id uuid primary key default gen_random_uuid(),
  owner_id uuid not null references public.profiles(id) on delete cascade,
  name varchar(90) not null check (length(trim(name)) >= 1),
  species varchar(40) not null default 'Cachorro',
  breed varchar(80) not null default '',
  care_notes varchar(1000) not null default '',
  created_at timestamptz not null default now()
);
create index pets_owner on public.pets(owner_id);

create table public.bookings (
  id uuid primary key default gen_random_uuid(),
  owner_id uuid not null references public.profiles(id),
  pet_id uuid not null references public.pets(id),
  visit_date date not null,
  visit_time text not null check (visit_time in ('08:00','09:00','10:00','11:00','13:00','14:00','15:00','16:00','17:00')),
  service varchar(100) not null default 'Visita em domicílio',
  details varchar(1000) not null default '',
  status text not null default 'pending' check (status in ('pending','confirmed','declined','cancelled')),
  created_at timestamptz not null default now()
);
create index bookings_owner on public.bookings(owner_id);
create index bookings_date on public.bookings(visit_date,visit_time);
-- Impede que duas solicitações reservem o mesmo horário, inclusive em corrida entre celulares.
create unique index booking_one_active_per_slot on public.bookings(visit_date,visit_time)
where status in ('pending','confirmed');

create table public.blocked_slots (
  id uuid primary key default gen_random_uuid(),
  visit_date date not null,
  visit_time text not null check (visit_time in ('08:00','09:00','10:00','11:00','13:00','14:00','15:00','16:00','17:00')),
  reason varchar(180) not null default 'Bloqueado pelo Matheus',
  created_by uuid references auth.users(id),
  created_at timestamptz not null default now(),
  unique(visit_date, visit_time)
);

-- Calendário público ANÔNIMO: não contém IDs, nome, endereço ou qualquer dado de cliente.
create table public.slot_occupancy (
  visit_date date not null,
  visit_time text not null,
  state text not null check (state in ('pending','confirmed','blocked')),
  primary key (visit_date,visit_time)
);

-- Função administrativa FORA do esquema público exposto à API.
create or replace function private.is_admin() returns boolean
language sql stable security definer set search_path = '' as $$
  select exists (select 1 from public.app_admins a where a.user_id = (select auth.uid()));
$$;
revoke all on function private.is_admin() from public, anon;
grant execute on function private.is_admin() to authenticated;

-- Cadastro automático do cliente após o registro de e-mail/senha no Auth.
create or replace function private.handle_new_user() returns trigger
language plpgsql security definer set search_path = '' as $$
begin
  insert into public.profiles (id,email,full_name,phone,street,house_number,neighborhood,city,reference,notes)
  values (new.id, coalesce(new.email,''),
    coalesce(new.raw_user_meta_data->>'full_name',''),
    coalesce(new.raw_user_meta_data->>'phone',''),
    coalesce(new.raw_user_meta_data->>'street',''),
    coalesce(new.raw_user_meta_data->>'house_number',''),
    coalesce(new.raw_user_meta_data->>'neighborhood',''),
    coalesce(new.raw_user_meta_data->>'city',''),
    coalesce(new.raw_user_meta_data->>'reference',''),
    coalesce(new.raw_user_meta_data->>'notes',''))
  on conflict (id) do nothing;
  return new;
end;
$$;
create trigger pata_user_created after insert on auth.users
for each row execute function private.handle_new_user();

-- Para contas criadas ANTES de executar o script, se houver.
insert into public.profiles (id,email,full_name)
select id,coalesce(email,''),coalesce(raw_user_meta_data->>'full_name','')
from auth.users
on conflict (id) do nothing;

-- Confere o horário no fuso brasileiro, inclusive no banco.
create or replace function private.validate_booking() returns trigger
language plpgsql set search_path = '' as $$
begin
  if extract(dow from new.visit_date) = 0 then
    raise exception 'Não atendemos aos domingos.';
  end if;
  if (new.visit_date + new.visit_time::time) at time zone 'America/Sao_Paulo' <= now() then
    raise exception 'Escolha um horário futuro.';
  end if;
  return new;
end;
$$;
create trigger booking_valid_time before insert on public.bookings
for each row execute function private.validate_booking();

-- O registro do horário público é sincronizado APÓS cada mudança de agendamento.
-- A chave primária protege também contra disputa entre bloqueio e nova reserva.
create or replace function private.sync_booking_slot() returns trigger
language plpgsql security definer set search_path = '' as $$
begin
  if tg_op = 'INSERT' then
    if new.status in ('pending','confirmed') then
      insert into public.slot_occupancy (visit_date,visit_time,state)
      values(new.visit_date,new.visit_time,new.status);
    end if;
  elsif tg_op = 'UPDATE' then
    if old.status in ('pending','confirmed') and
       (new.status not in ('pending','confirmed') or new.visit_date <> old.visit_date or new.visit_time <> old.visit_time) then
      delete from public.slot_occupancy
      where visit_date=old.visit_date and visit_time=old.visit_time and state in ('pending','confirmed');
    end if;
    if new.status in ('pending','confirmed') then
      if old.status in ('pending','confirmed') and old.visit_date=new.visit_date and old.visit_time=new.visit_time then
        update public.slot_occupancy set state=new.status
        where visit_date=new.visit_date and visit_time=new.visit_time;
      else
        insert into public.slot_occupancy(visit_date,visit_time,state)
        values(new.visit_date,new.visit_time,new.status);
      end if;
    end if;
  elsif tg_op = 'DELETE' then
    if old.status in ('pending','confirmed') then
      delete from public.slot_occupancy where visit_date=old.visit_date and visit_time=old.visit_time and state in ('pending','confirmed');
    end if;
  end if;
  return null;
end;
$$;
create trigger booking_occupancy after insert or update or delete on public.bookings
for each row execute function private.sync_booking_slot();

create or replace function private.sync_blocked_slot() returns trigger
language plpgsql security definer set search_path = '' as $$
begin
  if tg_op = 'INSERT' then
    insert into public.slot_occupancy(visit_date,visit_time,state)
    values(new.visit_date,new.visit_time,'blocked');
  elsif tg_op = 'DELETE' then
    delete from public.slot_occupancy
    where visit_date=old.visit_date and visit_time=old.visit_time and state='blocked';
  end if;
  return null;
end;
$$;
create trigger blocked_occupancy after insert or delete on public.blocked_slots
for each row execute function private.sync_blocked_slot();

-- Habilitar RLS é indispensável quando o site fica público no GitHub Pages.
alter table public.app_admins enable row level security;
alter table public.profiles enable row level security;
alter table public.client_tags enable row level security;
alter table public.pets enable row level security;
alter table public.manual_clients enable row level security;
alter table public.bookings enable row level security;
alter table public.blocked_slots enable row level security;
alter table public.slot_occupancy enable row level security;

-- Retira concessões padrão generosas. Depois libera somente o necessário.
revoke all on public.app_admins, public.profiles, public.client_tags, public.pets,
  public.bookings, public.blocked_slots, public.slot_occupancy, public.manual_clients from public,anon,authenticated;

grant select,insert,update,delete on public.manual_clients to authenticated;
grant select on public.app_admins to authenticated;
grant select on public.profiles to authenticated;
grant update(full_name,phone,street,house_number,neighborhood,city,reference,notes) on public.profiles to authenticated;
grant select,insert on public.pets to authenticated;
grant update(name,species,breed,care_notes) on public.pets to authenticated;
grant select,insert on public.bookings to authenticated;
grant update(status) on public.bookings to authenticated;
grant select,insert,update on public.client_tags to authenticated;
grant select,insert,delete on public.blocked_slots to authenticated;
grant select on public.slot_occupancy to anon, authenticated;

create policy "read admin assignment self" on public.app_admins for select to authenticated
using (user_id=(select auth.uid()));

create policy "read own profile or admin" on public.profiles for select to authenticated
using (id=(select auth.uid()) or (select private.is_admin()));
create policy "edit own profile or admin" on public.profiles for update to authenticated
using (id=(select auth.uid()) or (select private.is_admin()))
with check (id=(select auth.uid()) or (select private.is_admin()));

create policy "admin reads tags" on public.client_tags for select to authenticated
using ((select private.is_admin()));
create policy "admin inserts tags" on public.client_tags for insert to authenticated
with check ((select private.is_admin()));
create policy "admin updates tags" on public.client_tags for update to authenticated
using ((select private.is_admin())) with check ((select private.is_admin()));

create policy "admin reads manual clients" on public.manual_clients for select to authenticated
using ((select private.is_admin()));
create policy "admin adds manual clients" on public.manual_clients for insert to authenticated
with check ((select private.is_admin()));
create policy "admin edits manual clients" on public.manual_clients for update to authenticated
using ((select private.is_admin())) with check ((select private.is_admin()));
create policy "admin removes manual clients" on public.manual_clients for delete to authenticated
using ((select private.is_admin()));

create policy "read own pets or admin" on public.pets for select to authenticated
using (owner_id=(select auth.uid()) or (select private.is_admin()));
create policy "add own pet" on public.pets for insert to authenticated
with check (owner_id=(select auth.uid()));
create policy "edit own pet" on public.pets for update to authenticated
using (owner_id=(select auth.uid())) with check (owner_id=(select auth.uid()));

create policy "read own bookings or admin" on public.bookings for select to authenticated
using (owner_id=(select auth.uid()) or (select private.is_admin()));
create policy "book only own animal as pending" on public.bookings for insert to authenticated
with check (owner_id=(select auth.uid()) and status='pending' and
  exists (select 1 from public.pets p where p.id=pet_id and p.owner_id=(select auth.uid())));
create policy "owner can cancel" on public.bookings for update to authenticated
using (owner_id=(select auth.uid()) and status in ('pending','confirmed'))
with check (owner_id=(select auth.uid()) and status='cancelled');
create policy "admin manages bookings" on public.bookings for update to authenticated
using ((select private.is_admin())) with check ((select private.is_admin()));

create policy "admin reads blocks" on public.blocked_slots for select to authenticated
using ((select private.is_admin()));
create policy "admin inserts blocks" on public.blocked_slots for insert to authenticated
with check ((select private.is_admin()));
create policy "admin deletes blocks" on public.blocked_slots for delete to authenticated
using ((select private.is_admin()));

-- Estes dados não contêm informações pessoais.
create policy "anyone sees availability" on public.slot_occupancy for select to anon,authenticated
using (true);

-- Realtime: ocupação é pública; pedidos completos são distribuídos conforme as permissões RLS.
do $$
begin
  if exists (select 1 from pg_publication where pubname='supabase_realtime') then
    if not exists (select 1 from pg_publication_tables where pubname='supabase_realtime' and schemaname='public' and tablename='slot_occupancy') then
      alter publication supabase_realtime add table public.slot_occupancy;
    end if;
    if not exists (select 1 from pg_publication_tables where pubname='supabase_realtime' and schemaname='public' and tablename='bookings') then
      alter publication supabase_realtime add table public.bookings;
    end if;
  end if;
end $$;

-- PRÓXIMO PASSO, EM OUTRA CONSULTA SQL:
-- 1) Crie o usuário Matheus em Authentication > Users > Add user.
-- 2) Substitua o e-mail abaixo pelo dele e execute (SEM os dois hífens no início):
-- insert into public.app_admins(user_id)
-- select id from auth.users where email='EMAIL_REAL_DO_MATHEUS_AQUI'
-- on conflict (user_id) do nothing;
