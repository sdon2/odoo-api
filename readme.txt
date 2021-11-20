Odoo XML-RPC Client for PHP using Ripcord library
=================================================

Installation: composer require sdon2/odoo-api:dev-master

Usage:

$server_uri = "http://127.0.0.1:8081/";
$username = "info@example.com";
$password = "Password";
$db = "odoo_db";

$client = new OdooClient($server_uri, $username, $password, $db);

// Create record
$result = $client->createModel("res.partner", ['name' => 'New Partner']);

// Update record
$result = $client->updateModel("res.partner", 45, ['name' => 'Updated Partner']);

// Update multiple records
$result = $client->updateModel("res.partner", [45, 46, 47], ['name' => 'Updated Partners']);

// Delete record
$result = $client->deleteModel("res.partner", 45);

// Delete multiple records
$result = $client->deleteModel("res.partner", [45, 46, 47]);

// Search record by id
$result = $client->searchModelById("res.partner", 45);

// Search multiple records by id
$result = $client->searchModelById("res.partner", [45, 46, 47]);

// Search multiple models by parameters
$result = $client->searchModels("res.partner", [['name', '=', 'Saravana']]);

