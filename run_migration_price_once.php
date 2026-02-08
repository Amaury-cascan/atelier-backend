<?php
/**
 * Script à exécuter UNE SEULE FOIS sur le serveur pour ajouter la colonne price.
 * Utilise les identifiants du .env (daxa2805_marie).
 * SUPPRIMER CE FICHIER après exécution (sécurité).
 *
 * Usage : php run_migration_price_once.php
 *    ou : ouvrir dans le navigateur (si PHP en CLI pas dispo) puis SUPPRIMER le fichier.
 */

$envFile = __DIR__ . '/.env.local';
if (!is_file($envFile)) {
    $envFile = __DIR__ . '/.env';
}
if (!is_file($envFile)) {
    die("Fichier .env ou .env.local introuvable.\n");
}

$vars = [];
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') continue;
    if (strpos($line, '=') === false) continue;
    [$name, $value] = explode('=', $line, 2);
    $name = trim($name);
    $value = trim($value, " \t\"'");
    $vars[$name] = $value;
}

// Expansion des ${VAR} dans DATABASE_URL
$databaseUrl = $vars['DATABASE_URL'] ?? '';
while (preg_match('/\$\{(\w+)\}/', $databaseUrl, $m)) {
    $databaseUrl = str_replace($m[0], $vars[$m[1]] ?? '', $databaseUrl);
}

// Parse postgresql://user:pass@host:port/dbname
if (!preg_match('#postgresql://([^:]+):([^@]+)@([^:]+):(\d+)/([^?]+)#', $databaseUrl, $m)) {
    die("DATABASE_URL invalide dans .env\n");
}
[, $user, $pass, $host, $port, $dbname] = $m;
$dsn = "pgsql:host=$host;port=$port;dbname=$dbname";

try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    die("Connexion impossible : " . $e->getMessage() . "\n");
}

$queries = [
    'ALTER TABLE appointment ADD COLUMN IF NOT EXISTS price INT DEFAULT NULL',
    'UPDATE appointment a SET price = s.price FROM service s WHERE a.service_id = s.id',
    'ALTER TABLE appointment ALTER COLUMN service_id DROP NOT NULL',
];

foreach ($queries as $i => $sql) {
    try {
        $pdo->exec($sql);
        echo "OK (" . ($i + 1) . "/3) : " . substr($sql, 0, 60) . "...\n";
    } catch (PDOException $e) {
        die("Erreur requête " . ($i + 1) . " : " . $e->getMessage() . "\n");
    }
}

echo "\nMigration terminée. Pense à SUPPRIMER ce fichier (run_migration_price_once.php).\n";
echo "Puis exécuter : php bin/console doctrine:migrations:version 'DoctrineMigrations\\Version20260208140000' --add --no-interaction --env=prod\n";
