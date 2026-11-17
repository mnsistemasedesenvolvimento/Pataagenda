<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
// A GitHub Pages page may call this API directly, but never receives a MySQL password.
// Exact-origin CORS: no wildcard and no cross-domain cookies.
$githubOrigin = 'https://mnsistemasedesenvolvimento.github.io';
$requestOrigin = rtrim((string)($_SERVER['HTTP_ORIGIN'] ?? ''), '/');
$externalRequest = $requestOrigin === $githubOrigin;
// Área determinada pela rota e nunca usada como autorização: role do banco ainda é conferida.
$accessArea = (string)($_GET['area'] ?? 'client');
if (!in_array($accessArea, ['client', 'admin'], true)) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'Área de acesso inválida.']);
    exit;
}
$sessionName = $accessArea === 'admin' ? 'MPETADMSESS' : 'MPETCLISESS';
$rememberCookieName = $accessArea === 'admin' ? 'MPETADMREM' : 'MPETCLIREM';

if ($externalRequest) {
    header('Access-Control-Allow-Origin: '.$githubOrigin);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Pet-Token');
    header('Access-Control-Max-Age: 600');
} elseif ($requestOrigin !== '') {
    $originHost = strtolower((string)parse_url($requestOrigin, PHP_URL_HOST));
    $currentHost = strtolower(explode(':', (string)($_SERVER['HTTP_HOST'] ?? ''))[0]);
    if ($originHost !== $currentHost) {
        http_response_code(403);
        echo json_encode(['ok'=>false,'error'=>'Origem não autorizada.']);
        exit;
    }
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code($externalRequest ? 204 : 403);
    exit;
}

// Captura e descarta eventual espaço em branco/BOM antes do <?php no
// config.php. Sem isso, session_start() não consegue enviar os cabeçalhos
// e as respostas da API deixam de ser JSON válido.
$configFile = dirname(__DIR__) . '/_private/config.php';
if (!is_file($configFile)) {
    http_response_code(503);
    echo json_encode(['ok'=>false,'error'=>'Configuração ausente no cPanel: _private/config.php.'], JSON_UNESCAPED_UNICODE);
    exit;
}
ob_start();
try {
    $config = require $configFile;
} finally {
    $configOutput = ob_get_clean();
    if ($configOutput !== '') {
        error_log('PetCare config.php enviou conteúdo antes do PHP; conteúdo descartado para proteger a sessão.');
    }
}
if (!is_array($config) || !isset($config['db_host'],$config['db_name'],$config['db_user'],$config['db_pass'])) {
    http_response_code(503);
    echo json_encode(['ok'=>false,'error'=>'Configuração MySQL incompleta. Confira _private/config.php.'], JSON_UNESCAPED_UNICODE);
    exit;
}
date_default_timezone_set($config['timezone'] ?? 'America/Sao_Paulo');
$isHttps = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
$cookiePath = dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/api/index.php'));
$cookiePath = $cookiePath === '/' ? '/' : rtrim($cookiePath, '/') . '/';
if ($externalRequest) {
    // GitHub Pages is another origin. Do NOT rely on third-party PHP cookies.
    // Each request is authenticated by a revocable token, verified against MySQL.
    $_SESSION = ['csrf' => bin2hex(random_bytes(24))];
} else {
    ini_set('session.use_strict_mode', '1');
    session_name($sessionName);
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
}

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
    global $externalRequest;
    if (($_SERVER['REQUEST_METHOD']??'GET')!=='POST') fail('Use POST.',405);
    if ($externalRequest) {
        // Browser-origin authenticated tokens are not cookies; therefore no CSRF token is needed.
        // Only JSON POSTs from the exact trusted GitHub origin are accepted.
        if (!str_starts_with(strtolower((string)($_SERVER['CONTENT_TYPE']??'')), 'application/json'))
            fail('Use JSON no envio.',415);
        return;
    }
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
// External login tokens: 256 bits of random data, only SHA-256 hashes in MySQL.
// This is independent of PHP's first-party sessions, which remain supported on cPanel.
function externalUserFromHeader(): void {
    global $externalRequest;
    if (!$externalRequest) return;
    $token=(string)($_SERVER['HTTP_X_PET_TOKEN']??'');
    if ($token === '') return;
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) fail('Acesso inválido. Entre novamente.', 401);
    $record=query('SELECT user_id FROM pet_external_tokens WHERE token_hash=? AND expires_at>NOW() LIMIT 1',
                  [hash('sha256',$token)])->fetch();
    if (!$record) fail('Seu login expirou. Entre novamente.',401);
    $accountRole=query('SELECT role FROM pet_users WHERE id=?',[(int)$record['user_id']])->fetchColumn();
    if ($accountRole !== $GLOBALS['accessArea'])
        fail('Esta conta pertence a outra área. Entre pela opção correta.',403);
    $_SESSION['uid']=(int)$record['user_id'];
}
function externalAuthData(array $input, int $userId): array {
    global $externalRequest;
    if (!$externalRequest) {
        return ['remember_active' => applyRememberChoice($input,$userId)];
    }
    $remember=!empty($input['remember_me']);
    $ttl=$remember ? 2592000 : 43200;
    $raw=bin2hex(random_bytes(32));
    // Requires instalacao/atualizar_v10.sql. Failure is reported without exposing credentials.
    query('INSERT INTO pet_external_tokens(user_id,token_hash,expires_at) VALUES (?,?,DATE_ADD(NOW(), INTERVAL '.$ttl.' SECOND))',
        [$userId,hash('sha256',$raw)]);
    return ['api_token'=>$raw, 'remember_active'=>$remember, 'token_expires_in'=>$ttl];
}
function revokeExternalToken(): void {
    global $externalRequest;
    if (!$externalRequest) return;
    $token=(string)($_SERVER['HTTP_X_PET_TOKEN']??'');
    if (preg_match('/^[a-f0-9]{64}$/',$token))
        query('DELETE FROM pet_external_tokens WHERE token_hash=?',[hash('sha256',$token)]);
}

function me(): array {
    if (empty($_SESSION['uid'])) fail('Entre na sua conta.',401);
    $r=query('SELECT id,role,email,full_name,phone,street,house_number,neighborhood,city,reference,notes,favorite,monthly_frequency,created_at FROM pet_users WHERE id=?',[(int)$_SESSION['uid']])->fetch();
    if (!$r) {$_SESSION=[];fail('Sessão inválida. Entre novamente.',401);}
    return $r;
}
function client(): array {
    $r=me();
    if ($r['role']!=='client') fail('Esta conta é administrativa. Saia do painel para entrar como cliente.',403);
    return $r;
}
function admin(): array {
    $r=me(); if ($r['role']!=='admin') fail('Acesso exclusivo do Mateus.',403);
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
function registrationThrottle(): void {
    // Registrations are tracked separately from failed logins, avoiding lockouts after a successful signup.
    $ip=hash('sha256','signup:'.(string)($_SERVER['REMOTE_ADDR']??'unknown'));
    $count=(int)query('SELECT COUNT(*) FROM pet_auth_attempts WHERE ip_hash=? AND created_at>DATE_SUB(NOW(),INTERVAL 1 HOUR)',[$ip])->fetchColumn();
    if ($count>=12) fail('Muitos cadastros neste dispositivo ou rede. Tente novamente mais tarde.',429);
    query('INSERT INTO pet_auth_attempts(ip_hash) VALUES(?)',[$ip]);
}
function rememberCookiePath(): string {
    global $cookiePath;
    return $cookiePath;
}
function writeRememberCookie(string $value,int $expires): void {
    global $isHttps;
    setcookie($GLOBALS['rememberCookieName'],$value,[
        'expires'=>$expires,'path'=>rememberCookiePath(),'secure'=>$isHttps,
        'httponly'=>true,'samesite'=>'Lax'
    ]);
}
function forgetRememberCookie(): void {
    $cookie=(string)($_COOKIE[$GLOBALS['rememberCookieName']]??'');
    if ($cookie!=='') {
        $selector=explode('.',$cookie,2)[0];
        if (preg_match('/^[a-f0-9]{24}$/',$selector)) {
            try {query('DELETE FROM pet_remember_tokens WHERE selector=?',[$selector]);}
            catch(Throwable $e) {error_log('Pet Sitter token revoke: '.$e->getMessage());}
        }
    }
    writeRememberCookie('',time()-3600);
    unset($_COOKIE[$GLOBALS['rememberCookieName']]);
}
function createRememberToken(int $uid): bool {
    global $isHttps;
    if (!$isHttps) return false; // Never send a long-lived login token over plaintext HTTP.
    $selector=bin2hex(random_bytes(12));
    $validator=bin2hex(random_bytes(32));
    $expires=time()+60*60*24*30;
    try {
        query('DELETE FROM pet_remember_tokens WHERE user_id=? AND expires_at < NOW()',[$uid]);
        query('INSERT INTO pet_remember_tokens(user_id,selector,token_hash,expires_at) VALUES(?,?,?,DATE_ADD(NOW(),INTERVAL 30 DAY))',
          [$uid,$selector,hash('sha256',$validator)]);
        writeRememberCookie($selector.'.'.$validator,$expires);
        return true;
    } catch(Throwable $e) {
        error_log('Pet Sitter remember setup: '.$e->getMessage());
        return false;
    }
}
function restoreRememberedLogin(): void {
    if (!empty($_SESSION['uid'])) return;
    $cookie=(string)($_COOKIE[$GLOBALS['rememberCookieName']]??'');
    if (!preg_match('/^[a-f0-9]{24}\.[a-f0-9]{64}$/',$cookie)) return;
    [$selector,$validator]=explode('.',$cookie,2);
    try {
        $r=query('SELECT user_id,token_hash FROM pet_remember_tokens WHERE selector=? AND expires_at>NOW()',[$selector])->fetch();
        if(!$r || !hash_equals((string)$r['token_hash'],hash('sha256',$validator))){
            forgetRememberCookie();return;
        }
        $exists=query('SELECT id FROM pet_users WHERE id=? AND role=?',[(int)$r['user_id'],$GLOBALS['accessArea']])->fetchColumn();
        if (!$exists) {forgetRememberCookie();return;}
        if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
        $_SESSION['uid']=(int)$r['user_id'];
        $_SESSION['csrf']=bin2hex(random_bytes(24));
        // Rotate the verifier upon restoration; stolen old cookies cannot be reused.
        $new=bin2hex(random_bytes(32));
        query('UPDATE pet_remember_tokens SET token_hash=? WHERE selector=?',[hash('sha256',$new),$selector]);
        writeRememberCookie($selector.'.'.$new,time()+60*60*24*30);
    } catch(Throwable $e) {
        error_log('Pet Sitter remember restore: '.$e->getMessage());
    }
}
function applyRememberChoice(array $x,int $uid): bool {
    forgetRememberCookie();
    return !empty($x['remember_me']) && createRememberToken($uid);
}
function registerUser(array $x): never {
    if (!empty($_SESSION['uid'])) {
        $current=me();
        if ($current['role']==='admin') fail('Saia da conta de administrador antes de cadastrar um cliente.',403);
        fail('Você já está conectado. Saia para criar outra conta.',409);
    }
    registrationThrottle();
    $email=strtolower(required($x,'email',190));
    if (!filter_var($email,FILTER_VALIDATE_EMAIL)) fail('Informe um e-mail válido.');
    $pass=(string)($x['password']??'');
    if (strlen($pass)<8 || strlen($pass)>72) fail('A senha deve ter de 8 a 72 caracteres.');
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
        if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
        $_SESSION['uid']=(int)db()->lastInsertId();
        $_SESSION['csrf']=bin2hex(random_bytes(24));
        $auth=externalAuthData($x,(int)$_SESSION['uid']);
        ok(['user'=>me(),'csrf'=>$_SESSION['csrf']] + $auth);
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
        ok(['booking_id'=>$bookId,'message'=>'Solicitação enviada ao Mateus!']);
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
    if (!$externalRequest) restoreRememberedLogin();
    else externalUserFromHeader();
    if($verb==='POST')requirePost();
    if($action==='bootstrap'&&$verb==='GET'){
        ok(['api_version'=>'10.2','area'=>$accessArea,'csrf'=>$_SESSION['csrf'],'logged_in'=>!empty($_SESSION['uid']),'timezone'=>'America/Sao_Paulo','hours'=>workingHours()]);
    }
    if($action==='health'&&$verb==='GET'){
        db()->query('SELECT 1');
        // Checagem read-only de pré-requisitos do login; não expõe credenciais.
        $setup=[];
        foreach(['pet_external_tokens','pet_remember_tokens'] as $tableName) {
            $setup[$tableName]=(bool)query(
                'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',
                [$tableName]
            )->fetchColumn();
        }
        ok(['database'=>'connected','api_version'=>'10.2','login_tables'=>$setup,
            'ready_for_login'=>($setup['pet_external_tokens'] && $setup['pet_remember_tokens'])]);
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
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||$pass==='')fail('Informe o e-mail e a senha do cadastro.',422);
        $u=query('SELECT id,password_hash FROM pet_users WHERE email=?',[$email])->fetch();
        if(!$u || !password_verify($pass,(string)$u['password_hash'])){markBadLogin();fail('E-mail ou senha incorretos. Se não tem conta, use Criar cadastro.',401);}
        // Reject credentials used in the wrong access area BEFORE changing the session.
        $role=(string)query('SELECT role FROM pet_users WHERE id=?',[(int)$u['id']])->fetchColumn();
        $audience=clean($x['audience']??'',10);
        if ($audience==='client' && $role!=='client') fail('Esta conta é do administrador. Use a entrada Administrador.',403);
        if ($audience==='admin' && $role!=='admin') fail('Esta conta é de cliente. Use Quero agendar.',403);
        if ($audience!=='' && !in_array($audience,['client','admin'],true)) fail('Área de login inválida.',422);
        if ($role !== $GLOBALS['accessArea'])
            fail($GLOBALS['accessArea']==='client'?'Esta conta é do Mateus. Use Administrador.':'Esta conta é de cliente. Use Quero agendar.',403);
        resetBadLogin();
        if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
        $_SESSION['uid']=(int)$u['id'];$_SESSION['csrf']=bin2hex(random_bytes(24));
        $auth=externalAuthData($x,(int)$u['id']);
        ok(['user'=>me(),'csrf'=>$_SESSION['csrf']] + $auth);
    }
    if($action==='logout'&&$verb==='POST'){
        if ($externalRequest) revokeExternalToken();
        else forgetRememberCookie();
        $_SESSION=[];
        if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
        $_SESSION['csrf']=bin2hex(random_bytes(24));ok(['csrf'=>$_SESSION['csrf']]);
    }
    if($action==='me'&&$verb==='GET')ok(['user'=>me(),'csrf'=>$_SESSION['csrf']]);
    if($action==='update_profile'&&$verb==='POST')userProfile(data(),(int)client()['id']);
    if($action==='change_password'&&$verb==='POST'){
        $u=me();$x=data();
        $old=(string)($x['current_password']??'');
        $new=(string)($x['new_password']??'');
        if(strlen($new)<10||strlen($new)>72)fail('A nova senha deve ter de 10 a 72 caracteres.');
        if(hash_equals($old,$new))fail('Escolha uma senha diferente da atual.');
        $hash=query('SELECT password_hash FROM pet_users WHERE id=?',[(int)$u['id']])->fetchColumn();
        if(!$hash||!password_verify($old,(string)$hash))fail('A senha atual está incorreta.',401);
        query('UPDATE pet_users SET password_hash=? WHERE id=?',[password_hash($new,PASSWORD_DEFAULT),(int)$u['id']]);
        try{query('DELETE FROM pet_remember_tokens WHERE user_id=?',[(int)$u['id']]);}catch(Throwable $ignore){error_log('Pet Sitter token cleanup: '.$ignore->getMessage());}
        if ($externalRequest) {
            // Revoke access from other devices and renew current access after password change.
            query('DELETE FROM pet_external_tokens WHERE user_id=?',[(int)$u['id']]);
            $auth=externalAuthData(['remember_me'=>false],(int)$u['id']);
        } else {
            forgetRememberCookie();
            if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
            $auth=[];
        }
        $_SESSION['csrf']=bin2hex(random_bytes(24));
        ok(['message'=>'Senha alterada com sucesso.','csrf'=>$_SESSION['csrf']] + $auth);
    }

    if($action==='pets'&&$verb==='GET')ok(['pets'=>query('SELECT id,user_id,name,species,breed,care_notes,created_at FROM pet_pets WHERE user_id=? AND active=1 ORDER BY created_at DESC',[(int)client()['id']])->fetchAll()]);
    if($action==='pet_save'&&$verb==='POST')petUpsert(data(),(int)client()['id']);
    if($action==='pet_remove'&&$verb==='POST'){
        $u=client();$x=data();$id=(int)($x['id']??0);
        $has=query('SELECT id FROM pet_bookings WHERE pet_id=? AND user_id=? AND status IN(\'pending\',\'confirmed\') LIMIT 1',[$id,(int)$u['id']])->fetch();
        if($has)fail('Este pet possui visitas pendentes ou confirmadas. Cancele antes de excluir.',409);
        query('UPDATE pet_pets SET active=0 WHERE id=? AND user_id=?',[$id,(int)$u['id']]);ok();
    }
    if($action==='bookings'&&$verb==='GET')ok(['bookings'=>bookingItems(client())]);
    if($action==='book'&&$verb==='POST')book(data(),client());
    if($action==='cancel_booking'&&$verb==='POST')releaseBooking((int)(data()['id']??0),'cancelled',(int)client()['id']);

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
    if($action==='admin_day_availability'&&$verb==='POST'){
        admin();$x=data();
        $day=clean($x['visit_date']??'',10);
        $mode=clean($x['mode']??'',12);
        if(!in_array($mode,['open','close'],true))fail('Ação inválida.',422);
        $dt=DateTimeImmutable::createFromFormat('!Y-m-d',$day);
        $now=new DateTimeImmutable();
        if(!$dt||$dt->format('Y-m-d')!==$day||$dt<$now->setTime(0,0)||$dt>$now->modify('+365 days')||(int)$dt->format('w')===0)
          fail('Selecione uma data válida, de segunda a sábado.',422);
        $pdo=db();$changed=0;
        try {
            $pdo->beginTransaction();
            foreach(workingHours() as $hour) {
                // Never touch bookings (pending/confirmed) or slots in the past.
                if(new DateTimeImmutable($day.' '.$hour)<=$now)continue;
                if($mode==='close') {
                    $changed+=query('INSERT IGNORE INTO pet_slots(visit_date,visit_time,state) VALUES(?,?,\'blocked\')',[$day,$hour.':00'])->rowCount();
                } else {
                    $changed+=query('DELETE FROM pet_slots WHERE visit_date=? AND visit_time=? AND state=\'blocked\'',[$day,$hour.':00'])->rowCount();
                }
            }
            $pdo->commit();
            ok(['changed'=>$changed,'message'=>$mode==='open'?'Horários liberados.':'Horários livres bloqueados.']);
        } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
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
    fail('Rota de API desconhecida. Atualize os arquivos PHP/JS juntos e recarregue a página.',404);
} catch(PDOException $e) {
    error_log('PetCare SQL error '.$e->getCode().': '.$e->getMessage());
    $message=$e->getMessage();
    if ((string)($e->errorInfo[1] ?? '')==='1146'
        && (str_contains($message, 'pet_external_tokens') || str_contains($message, 'pet_remember_tokens'))) {
        fail('Falta instalar as tabelas do login. Importe instalacao/corrigir_tabelas_login.sql no banco petcare.',503);
    }
    fail('Ocorreu um erro no banco. Confira as tabelas e os logs PHP do cPanel.',500);
} catch(Throwable $e) {
    error_log('PetCare error: '.$e->getMessage());
    fail('Ocorreu um erro no servidor. Tente novamente.',500);
}
