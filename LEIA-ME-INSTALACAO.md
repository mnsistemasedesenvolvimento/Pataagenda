# Matheus Pet Care V5 — Instalação no cPanel com MySQL

**Leia antes de publicar.** Este projeto foi preparado para **PHP 8.1+ e MySQL 5.7+**.

O arquivo ZIP contém uma pasta `matheus-pet-care/`. Ela deve ficar **dentro de `public_html`**, sem substituir os arquivos antigos. Não remova `public_html/config.php`, `public_html/salvar.php`, `public_html/index.html`, nem o banco `marce7856_financely` existente em suas imagens.

## A. Faça um backup antes de mexer na hospedagem

1. No cPanel, abra **Assistente de backup / Backup Wizard** e salve o conteúdo atual.
2. Em phpMyAdmin, selecione seu banco antigo `marce7856_financely` e use **Exportar** para guardar uma cópia local, caso depois você queira excluir dados específicos desse sistema.
3. **Não execute nenhum DROP, DELETE ou TRUNCATE** no banco antigo. A V5 usa um banco **novo**.

## B. Crie um banco separado

1. No cPanel, procure **Assistente de banco de dados / Database Wizard** (às vezes aparece como "MySQL Database Wizard"). **Não tente criar o banco pelo phpMyAdmin.**
2. Nome sugerido para o banco: `petcare`.
3. Nome sugerido para o usuário MySQL: `petapp`.
4. Gere uma **senha forte** e guarde-a só com você.
5. Marque **ALL PRIVILEGES** para esse usuário somente nesse banco novo.
6. Confira os nomes completos criados pelo cPanel, que normalmente incluem o prefixo da conta, por exemplo:
   - Banco: `marce7856_petcare`
   - Usuário: `marce7856_petapp`
   Estes são exemplos; use o que o cPanel mostrar efetivamente.

## C. Crie as tabelas (não apaga nada)

1. Abra phpMyAdmin.
2. Na coluna esquerda, clique **somente no banco NOVO** `..._petcare`.
3. Clique na aba **Importar**, escolha `instalacao/instalar_mysql.sql` extraído do ZIP e clique **Executar**.
4. Deverão aparecer as seis tabelas com prefixo `pet_`: `pet_users`, `pet_pets`, `pet_bookings`, `pet_slots`, `pet_manual_clients`, `pet_auth_attempts`.
5. Esse SQL usa `CREATE TABLE IF NOT EXISTS`, **não contém comandos de exclusão**.

## D. Instale os arquivos do site

1. Abra cPanel > **Gerenciador de arquivos**.
2. Entre na pasta `public_html` (ou na pasta raiz de um domínio/subdomínio que você escolher).
3. Clique em **Carregar/Upload** e envie o arquivo `Matheus_Pet_Care_V5_cPanel.zip`.
4. Clique com o botão direito no ZIP e selecione **Extract / Extrair**.
5. O resultado correto é `public_html/matheus-pet-care/index.php`, `public_html/matheus-pet-care/cliente.php`, `public_html/matheus-pet-care/admin.php`, etc.
6. **Não extraia os arquivos soltos na raiz `public_html`** e não substitua o site antigo.

## E. Configure o banco no site

1. No Gerenciador de arquivos, abra a pasta `public_html/matheus-pet-care/_private`.
2. Edite `config.php`.
3. Substitua estes 3 valores:
   - `db_name` = nome completo do NOVO banco.
   - `db_user` = nome completo do NOVO usuário.
   - `db_pass` = senha do NOVO usuário MySQL.
4. Mantenha `db_host` como `localhost` (a menos que o seu provedor informe outro host).
5. Salve o arquivo. **Não compartilhe esta senha pelo ChatGPT, no GitHub ou em prints.**
6. Em `matheus-pet-care/_private/.htaccess` existe proteção para impedir acesso direto aos arquivos privados.

## F. Verifique a conexão com o banco

Abra no navegador o endereço do aplicativo seguido de:

`api/index.php?action=health`

Se tudo estiver certo, a resposta será:

`{"ok":true,"database":"connected"}`

Se der erro: confira credenciais do arquivo privado, permissões do usuário no banco novo e importação das tabelas. Para o site funcionar, é obrigatório usar HTTPS e PHP com extensões PDO_MySQL habilitadas.

## G. Ative o acesso do Matheus

1. Abra `https://SEU-DOMINIO/matheus-pet-care/cliente.php`.
2. Faça um cadastro **de verdade** com o e-mail e senha que Matheus usará como administrador. Essa conta entra inicialmente como cliente comum, por segurança.
3. No phpMyAdmin, selecione somente o NOVO banco `..._petcare`.
4. Abra a aba **SQL** e substitua `EMAIL_DO_MATHEUS` pelo e-mail cadastrado antes de executar:

```sql
UPDATE pet_users SET role='admin' WHERE email='EMAIL_DO_MATHEUS';
```

5. Confira se atualizou exatamente **1 linha**.
6. Saia da conta no site, entre por `admin.php` com o mesmo e-mail e senha.
7. Nunca transforme contas desconhecidas em administradoras.

## H. Teste com dois celulares

1. No primeiro aparelho, acesse a página inicial e entre como administrador.
2. Em outro aparelho ou navegador, entre na área de cliente com uma conta separada.
3. Cadastre um animal, consulte a agenda e envie uma solicitação.
4. No painel do Matheus, abra **Pedidos** e aprove/recuse.
5. Confira se o cliente vê o status e se o horário muda no calendário.
6. A disponibilidade é atualizada automaticamente a cada **15 segundos** nas telas abertas. O banco reserva cada horário de forma exclusiva, evitando dois pedidos para o mesmo horário.

## I. Sobre apagar dados antigos

- **Não há exclusão automática de registros antigos.** Esta versão começa com um banco próprio vazio.
- A imagem enviada mostra outro banco, `marce7856_financely`, que pode pertencer ao seu sistema financeiro. **Não apague esse banco até identificar exatamente quais registros quer remover e exportar um backup**.
- Após o novo sistema funcionar, é possível limpar apenas dados de teste do próprio Matheus Pet Care; isso deve ser feito em procedimento separado e com confirmação explícita.
- O painel permite excluir **contatos manuais** individualmente. Excluir contas registradas e histórico de pedidos será uma função de manutenção futura, com backup e confirmação adicionais.

## J. Atualizações futuras

- Este aplicativo usa **PHP** no servidor, então **não funciona no GitHub Pages**. O GitHub Desktop pode guardar o código-fonte, mas `Push origin` não envia automaticamente os arquivos para o cPanel.
- A forma mais simples por enquanto é enviar o ZIP novo ao Gerenciador de arquivos e extrair dentro da pasta correta. Para atualizações sem upload manual, podemos configurar Git no cPanel ou uma implantação automatizada depois.
- Mantenha uma cópia de `_private/config.php` antes de substituir arquivos numa versão futura para não perder as credenciais. **Nunca publique esse arquivo em repositório público.**

### Recursos desta versão

- Página inicial com acesso separado de Cliente e Administrador.
- Cadastro de cliente, endereço, WhatsApp, e-mail, senha protegida com hash.
- Cadastro e edição de pets; observações de cuidados.
- Calendário com horários livres, em análise, confirmados ou bloqueados.
- Pedidos de visita, aprovação, recusa e cancelamento.
- Painel profissional, lista de clientes, estrelas, recorrência mensal e cadastro manual.
- Importação de contatos de backup JSON da V2 (não transfere senhas, pets ou pedidos anteriores).
- Visual otimizado para celular com indicador animado do botão.

**Limitações conhecidas:** ainda não existe recuperação automática de senha por e-mail, envio de notificações WhatsApp, nem recorrência agendada automaticamente. O campo de recorrência apenas registra a quantidade de visitas por mês. A publicação e o teste com banco real dependem da sua configuração no cPanel.
