<?php

namespace OdooAPI;

use OdooAPI\Exceptions\OdooAuthException;
use OdooAPI\Exceptions\OdooException;
use OdooAPI\Exceptions\OdooModelNotFoundException;
use Ripcord\Ripcord;

class OdooClient
{
    protected $server_uri = "";
    protected $username = "";
    protected $password = "";
    protected $db = "";

    protected static $client;

    public function __construct($server_uri, $username, $password, $db)
    {
        $this->server_uri = $server_uri;
        $this->username = $username;
        $this->password = $password;
        $this->db = $db;
    }

    protected function getClient($endpoint)
    {
        $ep = $this->server_uri . "/xmlrpc/2/" . $endpoint;
        if (!self::$client || self::$client->_url !== $ep) {
            self::$client = Ripcord::client($ep, ['output_type' => 'json', 'encoding' => 'utf-8', 'version' => 'xmlrpc']);
        }
        return self::$client;
    }

    /**
     * Gets XML-RPC Common endpoint client for Common Operations
     * @return Ripcord_Client
     */
    protected function getCommon()
    {
        return $this->getClient("common");
    }

    /**
     * Gets XML-RPC Object endpoint client for Model Operations
     * @return Ripcord_Client
     */
    protected function getObject()
    {
        return $this->getClient("object");
    }

    /**
     * Gets UID to perform operations on models
     * @return int
     * @throws OdooAuthException
     */
    protected function getUid()
    {
        $uid = $this->getCommon()->authenticate($this->db, $this->username, $this->password, []);
        if (!is_numeric($uid)) {
            throw new OdooAuthException('Authentication failure. Please check Odoo Credentials');
        }
        return $uid;
    }

    /**
     * Processes result and throws exception if error happens
     * @param mixed $result
     * @return mixed
     * @throws OdooException
     */
    protected function processResult($result)
    {
        if (is_array($result) && array_key_exists('faultCode', $result)) {
            throw new OdooException($result['faultString']);
        }
        return $result;
    }

    /**
     * Executes operation on a specified model and returns result
     * @param string $model_name Model's name
     * @param string $operation Operation to perform
     * @param array $options Options based on Operation
     * @param array $extra_options Extra options based on Operation
     * @return mixed
     * @throws OdooModelNotFoundException|OdooException
     */
    protected function execute($model_name, $operation, array $options, array $extra_options = [])
    {
        try {
            $result = $this->getObject()->execute_kw($this->db, $this->getUid(), $this->password, $model_name, $operation, $options, $extra_options);
            return $this->processResult($result);
        } catch (\Exception $ex) {
            if (strpos($ex->getMessage(), 'Record does not exist') !== false) {
                throw new OdooModelNotFoundException('Model not found (or) deleted');
            } else {
                throw new OdooException($ex->getMessage(), $ex->getCode());
            }
        }
    }

    /**
     * Checks whether the user has permission to perform an operation on the model
     * @param string $model_name Model's name
     * @param string $operation Operation name - read, write, create, unlink
     * @return boolean true or false
     * @throws OdooException
     */
    public function checkModelPermissions($model_name, $operation)
    {
        return $this->execute($model_name, 'check_access_rights', [$operation]);
    }

    /**
     * Gets fields of a specific model for analysis
     * @param string $model_name Model's Name
     * @return mixed
     * @throws OdooException
     */
    public function getModelFields($model_name)
    {
        return $this->execute($model_name, 'fields_get', [], ['attributes' => ['string', 'help', 'type']]);
    }

    /**
     * Creates model using given data
     * @param string $model_name Model's Name
     * @param array $data Data to create Model
     * @return int Id of created model
     * @throws OdooException
     */
    public function createModel($model_name, array $data)
    {
        return $this->execute($model_name, OdooOperations::CREATE, [$data]);
    }

    /**
     * Updates a model/models with given data
     * @param string $model_name Model's Name
     * @param mixed $id Single Integer Id or Array of Integer ID's to perform update
     * @param array $data Data to update
     * @return mixed
     * @throws OdooException
     */
    public function updateModel($model_name, $id, array $data)
    {
        if (!is_array($id)) {
            $id = [(int)$id];
        }

        return $this->execute($model_name, OdooOperations::WRITE, [$id, $data]);
    }

    /**
     * Deletes a model/models with given data
     * @param string $model_name Model's Name
     * @param mixed $id Single Integer Id or Array of Integer ID's to perform delete
     * @return mixed
     * @throws OdooException
     */
    public function deleteModel($model_name, $id)
    {
        if (!is_array($id)) {
            $id = [(int)$id];
        }

        return $this->execute($model_name, OdooOperations::UNLINK, [$id]);
    }

    /**
     * Searches for a model/models with id with fields mentioned
     * @param string $model_name Model's Name
     * @param mixed $id Single Integer Id or Array of Integer ID's to perform search
     * @param array $fields Fields to return. If empty, all of the fields will be returned
     * @return mixed If $id is array, array of models will be returned, otherwise a single model will be returned
     * @throws OdooException
     */
    public function searchModelById($model_name, $id, $fields = [])
    {
        $return_single = false;

        if (!is_array($id)) {
            $id = [(int)$id];
            $return_single = true;
        }

        $result = $this->execute($model_name, OdooOperations::READ, [$id], ['fields' => $fields]);
        if ($result && $return_single) {
            return array_shift($result);
        } else {
            return $result;
        }
    }

    /**
     * Searches for a model/models with id with fields mentioned
     * @param string $model_name Model's Name
     * @param array $query Array of search parameters, like [['name', '=', true], ['company', '=', true]]
     * @param array $fields Fields to return. If empty, all of the fields will be returned
     * @param int $limit Number of models to return. If empty, 1000 models will be returned
     * @return array
     * @throws OdooException
     */
    public function searchModels($model_name, array $query, $fields = [], $limit = 1000)
    {
        return $this->execute($model_name, OdooOperations::SEARCH_READ, [$query], ['fields' => $fields], ['limit' => $limit]);
    }
}
