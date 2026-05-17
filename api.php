<?php
header('Content-Type: application/json');

$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'vraiheit';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $action = $_GET['action'] ?? 'get_all';
    
    if ($action === 'get_all') {
        // 1. Hole alle Komponenten-Definitionen vom MASTER (aframe_components)
        $stmt = $pdo->query("SELECT component_name, component_source FROM aframe_components");
        $masterComps = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 2. Hole alle Arbeits-Daten (Instanzen) aus den TEMPS (aframe_compotemps)
        $stmt = $pdo->query("SELECT component_name, entities FROM aframe_compotemps");
        $tempData = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
        
        $payload = [
            'components' => [],
            'entities' => []
        ];
        
        foreach ($masterComps as $row) {
            $name = $row['component_name'];
            $payload['components'][] = [
                'name' => $name,
                'source' => $row['component_source'],
                'is_initialized' => isset($tempData[$name])
            ];
            
            // Wenn in Temps vorhanden, lade die Instanzen
            if (isset($tempData[$name])) {
                $entities = json_decode($tempData[$name]['entities'], true) ?: [];
                foreach ($entities as $ent) {
                    $payload['entities'][] = [
                        'id' => $ent['id'] ?? uniqid('ent-'),
                        'componentName' => $name,
                        'position' => $ent['position'] ?? '0 0 0',
                        'style' => $ent['attributes'] ?? []
                    ];
                }
            }
        }
        echo json_encode($payload);
    } 
    elseif ($action === 'initialize_component' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $name = $data['component_name'] ?? '';
        
        // Prüfen ob bereits in Temps
        $check = $pdo->prepare("SELECT COUNT(*) FROM aframe_compotemps WHERE component_name = ?");
        $check->execute([$name]);
        if ($check->fetchColumn() == 0) {
            // Vom Master in Temps kopieren
            $stmt = $pdo->prepare("INSERT INTO aframe_compotemps (component_name, component_source, entities) 
                                   SELECT component_name, component_source, entities FROM aframe_components 
                                   WHERE component_name = ?");
            $stmt->execute([$name]);
            echo json_encode(['status' => 'success', 'message' => "Komponente '$name' wurde für die Bearbeitung initialisiert."]);
        } else {
            echo json_encode(['status' => 'info', 'message' => "Komponente '$name' ist bereits initialisiert."]);
        }
    }
    elseif ($action === 'update_entities' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $name = $data['component_name'] ?? '';
        $entities = $data['entities'] ?? [];
        
        // NUR in Temps updaten!
        $stmt = $pdo->prepare("UPDATE aframe_compotemps SET entities = ? WHERE component_name = ?");
        $stmt->execute([json_encode($entities), $name]);
        echo json_encode(['status' => 'success']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
