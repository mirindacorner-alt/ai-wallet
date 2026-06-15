<?php
/**
 * 💳 AI Wallet — Presupuesto autónomo para IA
 * Inventado por BaRtTt · Desarrollado por MIRINDA
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

$pdo = new PDO("mysql:host=DB_HOST;dbname=DB_NAME;charset=utf8mb4",
    'DB_USER', 'DB_PASS',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

// Auto-setup tables
$pdo->exec("CREATE TABLE IF NOT EXISTS ai_limites_agente (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agente_id INT NOT NULL,
    limite_mensual DECIMAL(12,2) DEFAULT 0,
    UNIQUE KEY (agente_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS ai_solicitudes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agente_id INT NOT NULL DEFAULT 1,
    importe DECIMAL(12,2) NOT NULL,
    motivo VARCHAR(255),
    estado ENUM('pendiente','aprobada','rechazada') DEFAULT 'pendiente',
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS ai_wallet (
    id INT AUTO_INCREMENT PRIMARY KEY,
    propietario VARCHAR(100) NOT NULL,
    ia_nombre VARCHAR(100) NOT NULL,
    saldo DECIMAL(12,2) DEFAULT 0.00,
    presupuesto_mensual DECIMAL(12,2) DEFAULT 100.00,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS ai_transacciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    wallet_id INT NOT NULL DEFAULT 1,
    tipo ENUM('gasto','ingreso') DEFAULT 'gasto',
    categoria ENUM('tokens_ia','hosting','apis','herramientas','dominios','varios') NOT NULL DEFAULT 'varios',
    concepto VARCHAR(255) NOT NULL,
    importe DECIMAL(12,2) NOT NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    aprobado TINYINT(1) DEFAULT 1,
    INDEX idx_wallet (wallet_id),
    INDEX idx_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Create default wallet if not exists
$w = $pdo->query("SELECT COUNT(*) FROM ai_wallet")->fetchColumn();
if ($w == 0) {
    $pdo->exec("INSERT INTO ai_wallet (propietario, ia_nombre, saldo, presupuesto_mensual) VALUES ('BaRtTt', 'MIRINDA', 0.00, 50.00)");
}

$action = $_GET['action'] ?? 'dashboard';

// Security: token validation for sensitive actions (GROK suggestion)
$API_TOKEN = 'mw_' . md5('mirinda_wallet_2026');
if (in_array($action, ['gastar','ingresar','aprobar_solicitud','limite'])) {
    $token = $_GET['token'] ?? $_SERVER['HTTP_X_WALLET_TOKEN'] ?? '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $token = $token ?: ($body['token'] ?? '');
    }
    // Allow from same origin (dashboard) or with valid token
    $origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
    $is_local = strpos($origin, 'nucleoaccumbens.es') !== false;
    if (!$is_local && $token !== $API_TOKEN) {
        die(json_encode(['error' => 'Token requerido para acciones sensibles', 'hint' => 'Usa header X-Wallet-Token o param token']));
    }
}

switch ($action) {
    case 'dashboard':
        $wallet = $pdo->query("SELECT * FROM ai_wallet WHERE id=1")->fetch();
        $mes = date('Y-m');
        $gastado = $pdo->prepare("SELECT COALESCE(SUM(importe),0) FROM ai_transacciones WHERE wallet_id=1 AND tipo='gasto' AND fecha >= ?");
        $gastado->execute(["$mes-01"]);
        $gastado_mes = $gastado->fetchColumn();
        
        $por_cat = $pdo->prepare("SELECT categoria, SUM(importe) as total, COUNT(*) as n FROM ai_transacciones WHERE wallet_id=1 AND tipo='gasto' AND fecha >= ? GROUP BY categoria ORDER BY total DESC");
        $por_cat->execute(["$mes-01"]);
        
        $ultimos = $pdo->query("SELECT * FROM ai_transacciones WHERE wallet_id=1 ORDER BY fecha DESC LIMIT 10")->fetchAll();
        
        echo json_encode([
            'wallet' => $wallet,
            'gastado_mes' => (float)$gastado_mes,
            'disponible' => $wallet['saldo'],
            'presupuesto' => $wallet['presupuesto_mensual'],
            'por_categoria' => $por_cat->fetchAll(),
            'ultimas' => $ultimos,
        ], JSON_PRETTY_PRINT);
        break;

    case 'gastar':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') die(json_encode(['error' => 'POST required']));
        $data = json_decode(file_get_contents('php://input'), true);
        $importe = (float)($data['importe'] ?? 0);
        $concepto = $data['concepto'] ?? '';
        $categoria = $data['categoria'] ?? 'varios';
        $agente = $data['agente'] ?? 'MIRINDA';
        
        if ($importe <= 0 || !$concepto) die(json_encode(['error' => 'Datos incompletos']));
        
        // Resolve agente_id
        $ag = $pdo->prepare("SELECT id FROM ai_agentes WHERE nombre=?"); $ag->execute([$agente]);
        $agente_id = $ag->fetchColumn() ?: 1;
        
        // Check limite agente
        $mes = date('Y-m');
        $lim = $pdo->prepare("SELECT limite_mensual FROM ai_limites_agente WHERE agente_id=?"); $lim->execute([$agente_id]);
        $limite = (float)$lim->fetchColumn();
        if ($limite > 0) {
            $ya_gastado = $pdo->prepare("SELECT COALESCE(SUM(importe),0) FROM ai_transacciones WHERE agente_id=? AND tipo='gasto' AND fecha>=?");
            $ya_gastado->execute([$agente_id, "$mes-01"]); $gastado_ag = (float)$ya_gastado->fetchColumn();
            if ($gastado_ag + $importe > $limite) {
                die(json_encode(['error'=>'Limite mensual superado','limite'=>$limite,'gastado'=>$gastado_ag,'restante'=>$limite-$gastado_ag,'agente'=>$agente]));
            }
        }
        
        // Check saldo
        $saldo = (float)$pdo->query("SELECT saldo FROM ai_wallet WHERE id=1")->fetchColumn();
        if ($importe > $saldo) die(json_encode(['error' => 'Saldo insuficiente', 'saldo' => $saldo]));
        
        $pdo->prepare("INSERT INTO ai_transacciones (wallet_id, tipo, categoria, concepto, importe, agente_id) VALUES (1,'gasto',?,?,?,?)")
            ->execute([$categoria, $concepto, $importe, $agente_id]);
        $pdo->prepare("UPDATE ai_wallet SET saldo = saldo - ? WHERE id=1")->execute([$importe]);
        
        // Alert if saldo < 20%
        $nuevo_saldo = $saldo - $importe;
        $presup = (float)$pdo->query("SELECT presupuesto_mensual FROM ai_wallet WHERE id=1")->fetchColumn();
        $alerta = ($nuevo_saldo < $presup * 0.2) ? '⚠️ Saldo bajo (<20%)' : null;
        
        echo json_encode(['ok'=>true, 'concepto'=>$concepto, 'importe'=>$importe, 'agente'=>$agente, 'saldo_restante'=>$nuevo_saldo, 'alerta'=>$alerta]);
        break;

    case 'ingresar':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') die(json_encode(['error' => 'POST required']));
        $data = json_decode(file_get_contents('php://input'), true);
        $importe = (float)($data['importe'] ?? 0);
        $concepto = $data['concepto'] ?? 'Recarga';
        
        $pdo->prepare("INSERT INTO ai_transacciones (wallet_id, tipo, categoria, concepto, importe) VALUES (1,'ingreso','varios',?,?)")
            ->execute([$concepto, $importe]);
        $pdo->exec("UPDATE ai_wallet SET saldo = saldo + $importe WHERE id=1");
        
        $saldo = $pdo->query("SELECT saldo FROM ai_wallet WHERE id=1")->fetchColumn();
        echo json_encode(['ok' => true, 'saldo' => $saldo]);
        break;

    case 'historial':
        $r = $pdo->query("SELECT t.*, a.nombre as agente FROM ai_transacciones t LEFT JOIN ai_agentes a ON t.agente_id=a.id WHERE t.wallet_id=1 ORDER BY t.fecha DESC LIMIT 50");
        echo json_encode($r->fetchAll(), JSON_PRETTY_PRINT);
        break;

    case 'agentes':
        $r = $pdo->query("SELECT a.*, (SELECT COALESCE(SUM(t.importe),0) FROM ai_transacciones t WHERE t.agente_id=a.id AND t.tipo='gasto' AND t.fecha >= '" . date('Y-m') . "-01') as gastado_mes FROM ai_agentes a ORDER BY a.id");
        echo json_encode($r->fetchAll(), JSON_PRETTY_PRINT);
        break;

    case 'limite':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $ag = $pdo->prepare("SELECT id FROM ai_agentes WHERE nombre=?"); $ag->execute([$data['agente'] ?? '']);
            $aid = $ag->fetchColumn() ?: 0;
            $limite = (float)($data['limite'] ?? 0);
            if ($aid && $limite > 0) {
                $pdo->prepare("INSERT INTO ai_limites_agente (agente_id, limite_mensual) VALUES (?,?) ON DUPLICATE KEY UPDATE limite_mensual=?")
                    ->execute([$aid, $limite, $limite]);
                echo json_encode(['ok'=>true, 'agente_id'=>$aid, 'limite'=>$limite]);
            } else { echo json_encode(['error'=>'Agente o limite invalido']); }
        } else {
            $r = $pdo->query("SELECT a.nombre, COALESCE(l.limite_mensual,0) as limite FROM ai_agentes a LEFT JOIN ai_limites_agente l ON l.agente_id=a.id");
            echo json_encode($r->fetchAll());
        }
        break;

    case 'solicitar_fondos':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') die(json_encode(['error'=>'POST required']));
        $data = json_decode(file_get_contents('php://input'), true);
        $agente = $data['agente'] ?? 'MIRINDA';
        $importe = (float)($data['importe'] ?? 0);
        $motivo = $data['motivo'] ?? '';
        $ag = $pdo->prepare("SELECT id FROM ai_agentes WHERE nombre=?"); $ag->execute([$agente]);
        $aid = $ag->fetchColumn() ?: 1;
        if ($importe <= 0) die(json_encode(['error'=>'Importe invalido']));
        $pdo->prepare("INSERT INTO ai_solicitudes (agente_id, importe, motivo) VALUES (?,?,?)")
            ->execute([$aid, $importe, $motivo]);
        echo json_encode(['ok'=>true, 'solicitud_id'=>$pdo->lastInsertId(), 'mensaje'=>"Solicitud de $importe€ registrada. BaRtTt será notificado."]);
        break;

    case 'solicitudes':
        $r = $pdo->query("SELECT s.*, a.nombre as agente FROM ai_solicitudes s LEFT JOIN ai_agentes a ON s.agente_id=a.id ORDER BY s.fecha DESC LIMIT 20");
        echo json_encode($r->fetchAll());
        break;

    case 'aprobar_solicitud':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') die(json_encode(['error'=>'POST required']));
        $data = json_decode(file_get_contents('php://input'), true);
        $sid = (int)($data['id'] ?? 0);
        $accion = $data['accion'] ?? 'aprobar'; // aprobar|rechazar
        if ($accion === 'aprobar') {
            $sol = $pdo->prepare("SELECT * FROM ai_solicitudes WHERE id=? AND estado='pendiente'"); $sol->execute([$sid]);
            $s = $sol->fetch();
            if (!$s) die(json_encode(['error'=>'Solicitud no encontrada o ya procesada']));
            $pdo->prepare("UPDATE ai_solicitudes SET estado='aprobada' WHERE id=?")->execute([$sid]);
            $pdo->prepare("UPDATE ai_wallet SET saldo = saldo + ? WHERE id=1")->execute([$s['importe']]);
            $pdo->prepare("INSERT INTO ai_transacciones (wallet_id,tipo,categoria,concepto,importe,agente_id) VALUES (1,'ingreso','varios',?,?,?)")
                ->execute(['Solicitud #'.$sid.' aprobada', $s['importe'], $s['agente_id']]);
            echo json_encode(['ok'=>true, 'saldo_nuevo'=>(float)$pdo->query("SELECT saldo FROM ai_wallet WHERE id=1")->fetchColumn()]);
        } else {
            $pdo->prepare("UPDATE ai_solicitudes SET estado='rechazada' WHERE id=?")->execute([$sid]);
            echo json_encode(['ok'=>true, 'rechazada'=>$sid]);
        }
        break;

    case 'chart':
        $mes = date('Y-m');
        $days = [];
        for ($d = 1; $d <= (int)date('d'); $d++) {
            $dia = sprintf('%s-%02d', $mes, $d);
            $g = $pdo->prepare("SELECT COALESCE(SUM(importe),0) FROM ai_transacciones WHERE wallet_id=1 AND tipo='gasto' AND DATE(fecha)=?");
            $g->execute([$dia]); $days[] = ['dia' => $d, 'gasto' => (float)$g->fetchColumn()];
        }
        // Acumulado
        $acum = 0;
        foreach($days as &$dd) { $acum += $dd['gasto']; $dd['acumulado'] = $acum; }
        echo json_encode(['mes' => $mes, 'dias' => $days]);
        break;

    case 'resumen':
        $wallet = $pdo->query("SELECT * FROM ai_wallet WHERE id=1")->fetch();
        $mes = date('Y-m');
        $g = $pdo->prepare("SELECT COALESCE(SUM(importe),0) FROM ai_transacciones WHERE wallet_id=1 AND tipo='gasto' AND fecha>=?");
        $g->execute(["$mes-01"]); $gasto_mes = (float)$g->fetchColumn();
        $por_agente = $pdo->query("SELECT a.nombre, COALESCE(SUM(t.importe),0) as total FROM ai_agentes a LEFT JOIN ai_transacciones t ON t.agente_id=a.id AND t.tipo='gasto' AND t.fecha >= '$mes-01' GROUP BY a.id,a.nombre")->fetchAll();
        echo json_encode(['saldo'=>$wallet['saldo'],'presupuesto'=>$wallet['presupuesto_mensual'],'gastado_mes'=>$gasto_mes,'por_agente'=>$por_agente]);
        break;

    default:
        echo json_encode(['endpoints' => ['dashboard','gastar','ingresar','historial']]);
}
