<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

$config = require dirname(__DIR__) . '/_private/config.php';
date_default_timezone_set($config['timezone'] ?? 'America/Sao_Paulo');
$isHttps = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
$cookiePath = dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/api/index.php'));
$cookiePath = $cookiePath === '/' ? '/' : rtrim($cookiePath, '/') . '/';
ini_set('session.use_strict_mode','1');
session_name('MPETCARESESS');
session_set_cookie_params([
    'lifetime' => 0, 'path' => $cookiePath, 'secure' => $isHttps,
    'httponly' => true, 'samesite' => 'Lax'
]);
session_start();
if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
if (isset($_SESSION['last_seen']) && time() - (int)$_SESSION['last_seen'] > 43200) {
    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION['csrf'] = bin2hex(random_bytes(24));
}
$_SESSION['last_seen'] = time();

function done(array $body, int $code=200): never {
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
function ok(array $data=[]): never { done(['ok'=>true] + $data); }
function fail(string $message, int $code=400): never { done(['ok'=>false,'error'=>$message],$code); }
function clean($v, int $max=255): string {
    $s=trim((string)($v??''));
    $s=preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $s) ?? '';
    return function_exists('mb_substr') ? mb_substr($s,0,$max,'UTF-8') : substr($s,0,$max);
}
function data(): array {
    $length=(int)($_SERVER['CONTENT_LENGTH']??0);
    if ($length > 120000) fail('Envio muito grande.',413);
    $value=json_decode(file_get_contents('php://input') ?: '{}',true);
    if (!is_array($value)) fail('Dados inválidos.');
    return $value;
}
function requirePost(): void {
    if (($_SERVER['REQUEST_METHOD']??'GET')!=='POST') fail('Use POST.',405);
    $token=(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'');
    if (!hash_equals((string)($_SESSION['csrf']??''), $token)) fail('Sessão expirada. Atualize a página.',419);
    $origin=$_SERVER['HTTP_ORIGIN']??'';
    if ($origin!=='') {
        $host=strtolower((string)parse_url($origin,PHP_URL_HOST));
        $site=strtolower((string)($_SERVER['HTTP_HOST']??''));
        $site=explode(':',$site)[0];
        if ($host!==$site) fail('Origem não permitida.',403);
    }
}
function db(): PDO {
    global $config;
    if (str_contains((string)$config['db_name'],'COLOQUE_') || str_contains((string)$config['db_user'],'COLOQUE_')) {
        fail('Banco ainda não configurado. O responsável deve preencher _private/config.php.',503);
    }
    static $pdo=null;
    if ($pdo instanceof PDO) return $pdo;
    try {
        $pdo=new PDO('mysql:host='.$config['db_host'].';dbname='.$config['db_name'].';charset=utf8mb4',
            $config['db_user'],$config['db_pass'],[
              PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
              PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
              PDO::ATTR_EMULATE_PREPARES=>false,
            ]);
        return $pdo;
    } catch(Throwable $e) {
        error_log('PetCare database unavailable: '.$e->getMessage());
        fail('Não foi possível conectar ao banco. Confira as credenciais e as permissões no cPanel.',503);
    }
}
function query(string $sql,array $params=[]): PDOStatement {
    $q=db()->prepare($sql); $q->execute($params);return $q;
}
function me(): array {
    if (empty($_SESSION['uid'])) fail('Entre na sua conta.',401);
    $r=query('SELECT id,role,email,full_name,phone,street,house_number,neighborhood,city,reference,notes,favorite,monthly_frequency,created_at FROM pet_users WHERE id=?',[(int)$_SESSION['uid']])->fetch();
    if (!$r) {$_SESSION=[];fail('Sessão inválida. Entre novamente.',401);}
    return $r;
}
function admin(): array {
    $r=me(); if ($r['role']!=='admin') fail('Acesso exclusivo do Matheus.',403);
    return $r;
}
function required(array $input,string $key,int $max=200): string {
    $v=clean($input[$key]??'', $max);
    if ($v==='') fail('Preencha o campo obrigatório: '.$key);
    return $v;
}
function phone(string $value): bool {return strlen(preg_replace('/\D/','',$value))>=10;}
function workingHours(): array {return ['08:00','09:00','10:00','11:00','13:00','14:00','15:00','16:00','17:00'];}
function validSlot(string $date,string $time): void {
    $d=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
    $now=new DateTimeImmutable('now');
    if (!$d || $d->format('Y-m-d')!==$date || $d<$now->setTime(0,0) || $d>$now->modify('+365 days')) fail('Escolha uma data futura válida.');
    if ((int)$d->format('w')===0) fail('Não há atendimento aos domingos.');
    if (!in_array($time,workingHours(),true)) fail('Selecione um horário válido.');
    if (new DateTimeImmutable($date.' '.$time)<=$now) fail('Esse horário já passou.');
}
function bookingItems(array $user,bool $isAdmin=false): array {
    if ($isAdmin) {
        return query('SELECT b.*,u.full_name AS client_name,u.phone AS client_phone,u.street,u.house_number,u.neighborhood,u.city,u.reference,p.name AS pet_name,p.species AS pet_species FROM pet_bookings b JOIN pet_users u ON u.id=b.user_id JOIN pet_pets p ON p.id=b.pet_id ORDER BY b.visit_date DESC,b.visit_time DESC LIMIT 1000')->fetchAll();
    }
    return query('SELECT b.*,p.name AS pet_name,p.species AS pet_species FROM pet_bookings b JOIN pet_pets p ON p.id=b.pet_id WHERE b.user_id=? ORDER BY b.visit_date DESC,b.visit_time DESC LIMIT 200',[(int)$user['id']])->fetchAll();
}
function authThrottle(): void {
    $ip=(string)($_SERVER['REMOTE_ADDR']??'unknown');
    $hash=hash('sha256',$ip);
    $c=(int)query('SELECT COUNT(*) FROM pet_auth_attempts WHERE ip_hash=? AND created_at>DATE_SUB(NOW(),INTERVAL 15 MINUTE)',[$hash])->fetchColumn();
    if ($c>=15) fail('Muitas tentativas. Tente novamente em 15 minutos.',429);
}
function markBadLogin(): void {
    $ip=hash('sha256',(string)($_SERVER['REMOTE_ADDR']??'unknown'));
    query('INSERT INTO pet_auth_attempts(ip_hash) VALUES(?)',[$ip]);
}
function resetBadLogin(): void {
    $ip=hash('sha256',(string)($_SERVER['REMOTE_ADDR']??'unknown'));
    query('DELETE FROM pet_auth_attempts WHERE ip_hash=?',[$ip]);
}
function registerUser(array $x): never {
    authThrottle();
    markBadLogin(); // limita a criação excessiva de contas por IP
    $email=strtolower(required($x,'email',190));
    if (!filter_var($email,FILTER_VALIDATE_EMAIL)) fail('Informe um e-mail válido.');
    $pass=(string)($x['password']??'');
    if (strlen($pass)<10 || strlen($pass)>72) fail('A senha deve ter de 10 a 72 caracteres.');
    $name=required($x,'full_name',120); $tel=required($x,'phone',25);
    if(!phone($tel)) fail('Digite o WhatsApp com DDD.');
    $street=required($x,'street',160);$num=required($x,'house_number',25);
    $city=required($x,'city',100);
    $neighborhood=clean($x['neighborhood']??'',90);
    $reference=clean($x['reference']??'',180);
    $notes=clean($x['notes']??'',1000);
    try{
        query('INSERT INTO pet_users (role,email,password_hash,full_name,phone,street,house_number,neighborhood,city,reference,notes) VALUES (\'client\',?,?,?,?,?,?,?,?,?,?)',
          [$email,password_hash($pass,PASSWORD_DEFAULT),$name,$tel,$street,$num,$neighborhood,$city,$reference,$notes]);
        session_regenerate_id(true);
        $_SESSION['uid']=(int)db()->lastInsertId();
        $_SESSION['csrf']=bin2hex(random_bytes(24));
        ok(['user'=>me(),'csrf'=>$_SESSION['csrf']]);
    }catch(PDOException $e){if($e->getCode()==='23000')fail('Este e-mail já está cadastrado. Entre com sua senha.',409);throw $e;}
}
function userProfile(array $x,int $id): never {
    $name=required($x,'full_name',120);$tel=required($x,'phone',25);
    if(!phone($tel))fail('Digite o WhatsApp com DDD.');
    query('UPDATE pet_users SET full_name=?,phone=?,street=?,house_number=?,neighborhood=?,city=?,reference=?,notes=? WHERE id=?',
      [$name,$tel,required($x,'street',160),required($x,'house_number',25),clean($x['neighborhood']??'',90),required($x,'city',100),clean($x['reference']??'',180),clean($x['notes']??'',1000),$id]);
    ok(['user'=>me()]);
}
function petUpsert(array $x,int $owner): never {
    $name=required($x,'name',100);
    $species=required($x,'species',45);
    $breed=clean($x['breed']??'',90);$notes=clean($x['care_notes']??'',3000);
    $id=(int)($x['id']??0);
    if ($id) {
       $n=query('UPDATE pet_pets SET name=?,species=?,breed=?,care_notes=? WHERE id=? AND user_id=? AND active=1',[$name,$species,$breed,$notes,$id,$owner])->rowCount();
       if (!$n) {
          $has=query('SELECT id FROM pet_pets WHERE id=? AND user_id=? AND active=1',[$id,$owner])->fetch();
          if (!$has) fail('Pet não encontrado.',404);
       }
    } else {
       query('INSERT INTO pet_pets(user_id,name,species,breed,care_notes) VALUES(?,?,?,?,?)',[$owner,$name,$species,$breed,$notes]);
       $id=(int)db()->lastInsertId();
    }
    ok(['pet_id'=>$id]);
}
function book(array $x,array $u): never {
    $petId=(int)($x['pet_id']??0);
    $has=query('SELECT id FROM pet_pets WHERE id=? AND user_id=? AND active=1',[$petId,(int)$u['id']])->fetch();
    if (!$has) fail('Cadastre e selecione um pet antes de solicitar.');
    $date=clean($x['visit_date']??'',10);$time=clean($x['visit_time']??'',5);
    validSlot($date,$time);
    $service=required($x,'service',70);
    if(!in_array($service,['Visita domiciliar','Passeio','Alimentação','Cuidados e higiene','Companhia e cuidados','Outro cuidado'],true))fail('Selecione um serviço válido.');
    $notes=clean($x['details']??'',3000);
    $pdo=db();
    try{
        $pdo->beginTransaction();
        query('INSERT INTO pet_bookings(user_id,pet_id,visit_date,visit_time,service,details,status) VALUES(?,?,?,?,?,?,\'pending\')',[(int)$u['id'],$petId,$date,$time.':00',$service,$notes]);
        $bookId=(int)$pdo->lastInsertId();
        query('INSERT INTO pet_slots(visit_date,visit_time,state,booking_id) VALUES(?,?,\'pending\',?)',[$date,$time.':00',$bookId]);
        $pdo->commit();
        ok(['booking_id'=>$bookId,'message'=>'Solicitação enviada ao Matheus!']);
    }catch(PDOException $e){if($pdo->inTransaction())$pdo->rollBack();if($e->getCode()==='23000')fail('Esse horário acabou de ser reservado. Escolha outro.',409);throw $e;}
}
function releaseBooking(int $id, string $state, int $owner=0): never {
    $pdo=db();
    $pdo->beginTransaction();
    try{
        $r=query('SELECT user_id,status,visit_date,visit_time FROM pet_bookings WHERE id=? FOR UPDATE',[$id])->fetch();
        if(!$r || ($owner && (int)$r['user_id']!==$owner)) { $pdo->rollBack();fail('Solicitação não encontrada.',404); }
        if(!in_array($r['status'],['pending','confirmed'],true)){$pdo->rollBack();fail('Este pedido já foi finalizado.',409);}
        if($owner && $state!=='cancelled'){$pdo->rollBack();fail('Ação não permitida.',403);}
        if($state==='confirmed') {
            query('UPDATE pet_slots SET state=\'confirmed\' WHERE booking_id=?',[$id]);
        } elseif(in_array($state,['declined','cancelled'],true)) {
            query('DELETE FROM pet_slots WHERE booking_id=?',[$id]);
        } else {$pdo->rollBack();fail('Situação inválida.');}
        query('UPDATE pet_bookings SET status=? WHERE id=?',[$state,$id]);
        $pdo->commit();ok(['message'=>'Pedido atualizado.']);
    } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function manualSave(array $x): never {
    $id=(int)($x['id']??0);$freq=(int)($x['monthly_frequency']??0);
    if($freq<0||$freq>31)fail('Frequência deve ser de 0 a 31.');
    $v=[required($x,'full_name',120),clean($x['phone']??'',25),clean($x['street']??'',160),clean($x['house_number']??'',25),clean($x['neighborhood']??'',90),clean($x['city']??'',100),clean($x['reference']??'',180),clean($x['notes']??'',3000),!empty($x['favorite'])?1:0,$freq];
    if($id)query('UPDATE pet_manual_clients SET full_name=?,phone=?,street=?,house_number=?,neighborhood=?,city=?,reference=?,notes=?,favorite=?,monthly_frequency=? WHERE id=?',[...$v,$id]);
    else {query('INSERT INTO pet_manual_clients(full_name,phone,street,house_number,neighborhood,city,reference,notes,favorite,monthly_frequency) VALUES(?,?,?,?,?,?,?,?,?,?)',$v);$id=(int)db()->lastInsertId();}
    ok(['id'=>$id]);
}
try {
    $action=clean($_GET['action']??'',50);
    $verb=$_SERVER['REQUEST_METHOD']??'GET';
    if($verb==='POST')requirePost();
    if($action==='bootstrap'&&$verb==='GET'){
        ok(['csrf'=>$_SESSION['csrf'],'logged_in'=>!empty($_SESSION['uid']),'timezone'=>'America/Sao_Paulo','hours'=>workingHours()]);
    }
    if($action==='health'&&$verb==='GET'){
        db()->query('SELECT 1');ok(['database'=>'connected']);
    }
    if($action==='availability'&&$verb==='GET'){
        $month=clean($_GET['month']??date('Y-m'),7);
        if(!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$month))fail('Mês inválido.');
        $begin=new DateTimeImmutable($month.'-01');
        if ($begin < new DateTimeImmutable('first day of -1 month') || $begin > new DateTimeImmutable('first day of +12 months')) fail('Mês fora do intervalo.');
        $r=query('SELECT DATE_FORMAT(visit_date,\'%Y-%m-%d\') AS visit_date, TIME_FORMAT(visit_time,\'%H:%i\') AS visit_time,state FROM pet_slots WHERE visit_date >= ? AND visit_date < DATE_ADD(?,INTERVAL 1 MONTH)',[$begin->format('Y-m-d'),$begin->format('Y-m-d')])->fetchAll();
        ok(['slots'=>$r,'month'=>$month,'server_now'=>date('c')]);
    }
    if($action==='register'&&$verb==='POST')registerUser(data());
    if($action==='login'&&$verb==='POST'){
        authThrottle();$x=data();$email=strtolower(clean($x['email']??'',190));$pass=(string)($x['password']??'');
        $u=query('SELECT id,password_hash FROM pet_users WHERE email=?',[$email])->fetch();
        if(!$u || !password_verify($pass,$u['password_hash'])){markBadLogin();fail('E-mail ou senha incorretos.',401);}
        resetBadLogin();session_regenerate_id(true);
        $_SESSION['uid']=(int)$u['id'];$_SESSION['csrf']=bin2hex(random_bytes(24));
        ok(['user'=>me(),'csrf'=>$_SESSION['csrf']]);
    }
    if($action==='logout'&&$verb==='POST'){
        $_SESSION=[];session_regenerate_id(true);
        $_SESSION['csrf']=bin2hex(random_bytes(24));ok(['csrf'=>$_SESSION['csrf']]);
    }
    if($action==='me'&&$verb==='GET')ok(['user'=>me(),'csrf'=>$_SESSION['csrf']]);
    if($action==='update_profile'&&$verb==='POST')userProfile(data(),(int)me()['id']);
    if($action==='pets'&&$verb==='GET')ok(['pets'=>query('SELECT id,user_id,name,species,breed,care_notes,created_at FROM pet_pets WHERE user_id=? AND active=1 ORDER BY created_at DESC',[(int)me()['id']])->fetchAll()]);
    if($action==='pet_save'&&$verb==='POST')petUpsert(data(),(int)me()['id']);
    if($action==='pet_remove'&&$verb==='POST'){
        $u=me();$x=data();$id=(int)($x['id']??0);
        $has=query('SELECT id FROM pet_bookings WHERE pet_id=? AND user_id=? AND status IN(\'pending\',\'confirmed\') LIMIT 1',[$id,(int)$u['id']])->fetch();
        if($has)fail('Este pet possui visitas pendentes ou confirmadas. Cancele antes de excluir.',409);
        query('UPDATE pet_pets SET active=0 WHERE id=? AND user_id=?',[$id,(int)$u['id']]);ok();
    }
    if($action==='bookings'&&$verb==='GET')ok(['bookings'=>bookingItems(me())]);
    if($action==='book'&&$verb==='POST')book(data(),me());
    if($action==='cancel_booking'&&$verb==='POST')releaseBooking((int)(data()['id']??0),'cancelled',(int)me()['id']);

    if($action==='admin_dashboard'&&$verb==='GET'){
        admin();
        $counts=[
            'clients'=>(int)query('SELECT COUNT(*) FROM pet_users WHERE role=\'client\'')->fetchColumn()+(int)query('SELECT COUNT(*) FROM pet_manual_clients')->fetchColumn(),
            'pending'=>(int)query('SELECT COUNT(*) FROM pet_bookings WHERE status=\'pending\'')->fetchColumn(),
            'pets'=>(int)query('SELECT COUNT(*) FROM pet_pets WHERE active=1')->fetchColumn(),
            'confirmed'=>(int)query('SELECT COUNT(*) FROM pet_bookings WHERE status=\'confirmed\'')->fetchColumn(),
        ];
        ok(['counts'=>$counts,'bookings'=>bookingItems(me(),true)]);
    }
    if($action==='admin_clients'&&$verb==='GET'){
        admin();$clients=query('SELECT id,full_name,email,phone,street,house_number,neighborhood,city,reference,notes,favorite,monthly_frequency,admin_notes,created_at FROM pet_users WHERE role=\'client\' ORDER BY full_name')->fetchAll();
        $manual=query('SELECT * FROM pet_manual_clients ORDER BY full_name')->fetchAll();
        $pets=query('SELECT p.id,p.user_id,p.name,p.species,p.breed,p.care_notes FROM pet_pets p WHERE p.active=1')->fetchAll();
        ok(['clients'=>$clients,'manual'=>$manual,'pets'=>$pets]);
    }
    if($action==='admin_tag'&&$verb==='POST'){
        admin();$x=data();$id=(int)($x['id']??0);$freq=(int)($x['monthly_frequency']??0);
        if($freq<0||$freq>31)fail('Frequência deve ser de 0 a 31.');
        query('UPDATE pet_users SET favorite=?,monthly_frequency=?,admin_notes=? WHERE id=? AND role=\'client\'',
          [!empty($x['favorite'])?1:0,$freq,clean($x['admin_notes']??'',3000),$id]);ok();
    }
    if($action==='admin_manual_save'&&$verb==='POST'){admin();manualSave(data());}
    if($action==='admin_manual_bulk'&&$verb==='POST'){
        admin();$x=data();$list=$x['clients']??[];
        if(!is_array($list)||count($list)>200)fail('Importe no máximo 200 contatos de cada vez.');
        $pdo=db();$created=0;$pdo->beginTransaction();
        try{
            foreach($list as $c){
                if(!is_array($c))continue;
                $name=clean($c['full_name']??'',120);$phone=clean($c['phone']??'',25);
                if($name==='')continue;
                $existing=query('SELECT id FROM pet_manual_clients WHERE full_name=? AND phone=? LIMIT 1',[$name,$phone])->fetch();
                if($existing)continue;
                $freq=min(31,max(0,(int)($c['monthly_frequency']??0)));
                query('INSERT INTO pet_manual_clients(full_name,phone,street,house_number,neighborhood,city,reference,notes,favorite,monthly_frequency) VALUES(?,?,?,?,?,?,?,?,?,?)',
                 [$name,$phone,clean($c['street']??'',160),clean($c['house_number']??'',25),clean($c['neighborhood']??'',90),clean($c['city']??'',100),clean($c['reference']??'',180),clean($c['notes']??'',3000),!empty($c['favorite'])?1:0,$freq]);
                $created++;
            }
            $pdo->commit();ok(['created'=>$created]);
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
    if($action==='admin_manual_delete'&&$verb==='POST'){
        admin();$id=(int)(data()['id']??0);
        query('DELETE FROM pet_manual_clients WHERE id=?',[$id]);ok();
    }
    if($action==='admin_status'&&$verb==='POST'){
        admin();$x=data();$state=clean($x['status']??'',20);
        if(!in_array($state,['confirmed','declined'],true))fail('Ação não permitida.');
        releaseBooking((int)($x['id']??0),$state);
    }
    if($action==='admin_toggle_block'&&$verb==='POST'){
        admin();$x=data();$date=clean($x['visit_date']??'',10);$time=clean($x['visit_time']??'',5);
        validSlot($date,$time);
        $slot=query('SELECT state FROM pet_slots WHERE visit_date=? AND visit_time=?',[$date,$time.':00'])->fetch();
        if($slot){
            if($slot['state']!=='blocked')fail('Existe uma solicitação nesse horário. Não pode bloquear.',409);
            query('DELETE FROM pet_slots WHERE visit_date=? AND visit_time=? AND state=\'blocked\'',[$date,$time.':00']);ok(['state'=>'free']);
        }
        try {query('INSERT INTO pet_slots(visit_date,visit_time,state) VALUES(?,?,\'blocked\')',[$date,$time.':00']);ok(['state'=>'blocked']);}
        catch(PDOException $e){if($e->getCode()==='23000')fail('Este horário acabou de ficar indisponível.',409);throw $e;}
    }
    fail('Recurso não encontrado.',404);
} catch(PDOException $e) {
    error_log('PetCare SQL error '.$e->getCode().': '.$e->getMessage());
    fail('Ocorreu um erro no banco. Verifique se o SQL de instalação foi executado.',500);
} catch(Throwable $e) {
    error_log('PetCare error: '.$e->getMessage());
    fail('Ocorreu um erro no servidor. Tente novamente.',500);
}
