<?php
/**
 * iZ³ | Izzzio blockchain - https://izzz.io
 * BitCoen project - https://bitcoen.io
 * @author: Andrey Nedobylsky (admin@twister-vl.ru)
 */

class BitcoenRPC
{
    private $_baseUrl = 'http://localhost:3001/';
    const METHODS = [
        'getInfo'            => ['httpMethod' => 'get'],
        'createWallet'       => ['httpMethod' => 'post'],
        'changeWallet'       => ['httpMethod' => 'post'],
        'getTransactions'    => ['httpMethod' => 'get'],
        'createTransaction'  => ['httpMethod' => 'post'],
        'instantTransaction' => ['httpMethod' => 'post'],
        'getWalletInfo'      => ['httpMethod' => 'get'],
    ];

    const TINY_ADDRESS_PREFIX = 'BL_';

    const MIL_TO_BEN = 10000000000;

    /**
     * cURL request
     * @param string $method
     * @param string $url
     * @param array $params
     * @return mixed|string
     */
    private static function curlRequest($method = 'get', $url, $params = [])
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        $response = curl_exec($ch);


        if ($response === false) {
            $response = curl_error($ch);
        }

        curl_close($ch);

        return $response;
    }

    /**
     * Make RPC request
     * @param string $method
     * @param array $params
     * @param string $paramStr
     * @return array|mixed
     * @throws InvalidMethodException
     * @throws ReturnException
     * @throws RpcCallException
     */
    private function request($method, $params = [], $paramStr = '')
    {
        if (empty(self::METHODS[$method])) {
            throw new InvalidMethodException('Invalid method ' . $method);
        }

        $responseBody = self::curlRequest(self::METHODS[$method]['httpMethod'], $this->_baseUrl . $method . $paramStr, $params);
        if (in_array(strtolower($responseBody), ['true', 'false'])) {
            if (strtolower($responseBody) === 'true') {
                return ['status' => 'ok'];
            } else {
                throw new ReturnException('Can\'t call method ' . $method);
            }
        }
        $response = json_decode($responseBody, true);
        if (!is_array($response)) {
            throw new RpcCallException('RPC Error: ' . $responseBody);
        }

        return $response;
    }

    /**
     * BitcoenRPC constructor.
     * @param string $RPCUrl
     */
    public function __construct($RPCUrl = 'http://localhost:3001/')
    {
        $this->_baseUrl = $RPCUrl;

        return $this;
    }

    /**
     * Returns current blockchain status and node info
     * @return mixed
     * @throws InvalidMethodException
     * @throws ReturnException
     * @throws RpcCallException
     */
    public function getInfo()
    {
        return $this->request('getInfo');
    }

    /**
     * Generate and register new wallet with id, block id, private and public keysPair
     * @return mixed
     * @throws InvalidMethodException
     * @throws ReturnException
     * @throws RpcCallException
     */
    public function createWallet()
    {
        return $this->request('createWallet');
    }

    /**
     * Change current wallet for node. The transactions list was recalculated Which can take a long time
     * @param string $id Full wallet address
     * @param string $private Private key
     * @param string $public Public key
     * @return array
     * @throws InvalidMethodException
     * @throws ReturnException
     * @throws RpcCallException
     */
    public function changeWalletByData($id, $private, $public)
    {
        if ($this->getWallet() === $id) {
            return ['status' => 'ok'];
        }

        return $this->request('changeWallet', [
            'id'      => $id,
            'public'  => $public,
            'private' => $private,
            'balance' => 0,
        ]);
    }

    /**
     * Change current wallet for node. The transactions list was recalculated Which can take a long time
     * @param array $wallet Array returned from create wallet method
     * @return array
     * @throws InvalidMethodException
     * @throws ReturnException
     * @throws RpcCallException
     */
    public function changeWallet($wallet)
    {
        return $this->changeWalletByData($wallet['id'], $wallet['keysPair']['private'], $wallet['keysPair']['public']);
    }

    /**
     * Get current wallet address
     * @return mixed
     * @throws InvalidMethodException
     * @throws ReturnException
     * @throws RpcCallException
     */
    public function getWallet()
    {
        return $this->getInfo()['wallet']['id'];
    }

    /**
     * Creates transaction from current wallet. Throws ReturnException if creation error
     * @param string $to Transaction recipient full address
     * @param int $amount Transaction amount in Mil
     * @param int|string $activationTimestamp Transaction activation timestamp, or "now" for instant activation
     * @return array
     * @throws InvalidMethodException
     * @throws ReturnException
     * @throws RpcCallException
     */
    public function createTransaction($to, $amount, $activationTimestamp = 'now')
    {
        if (strtolower($activationTimestamp) === 'now') {
            $activationTimestamp = time() * 1000;
        }

        return $this->request('createTransaction', [
            'id'            => $to,
            'amount'        => $amount,
            'fromTimestamp' => $activationTimestamp,
        ]);
    }


    /**
     * Creates transaction from specefied wallet. Throws ReturnException if creation error
     * @param string $to Transaction recipient full address
     * @param int $amount Transaction amount in Mil
     * @param string $from Sender full address
     * @param string $public Public key
     * @param string $private Private key
     * @param int|string $activationTimestamp Transaction activation timestamp, or "now" for instant activation
     * @return array
     * @throws InvalidMethodException
     * @throws ReturnException
     * @throws RpcCallException
     */
    public function instantTransactionData($to, $amount, $from, $public, $private, $activationTimestamp = 'now')
    {
        if (strtolower($activationTimestamp) === 'now') {
            $activationTimestamp = time() * 1000;
        }

        return $this->request('instantTransaction', [
            'id'            => $to,
            'from'          => $from,
            'amount'        => $amount,
            'fromTimestamp' => $activationTimestamp,
            'public'        => $public,
            'private'       => $private,
        ]);
    }

    /**
     * Creates transaction from specefied wallet. Throws ReturnException if creation error
     * @param string $to Transaction recipient full address
     * @param int $amount Transaction amount in Mil
     * @param array $wallet Wallet array from createWallet method
     * @param int|string $activationTimestamp Transaction activation timestamp, or "now" for instant activation
     * @return array
     * @throws InvalidMethodException
     * @throws ReturnException
     * @throws RpcCallException
     */
    public function instantTransaction($to, $amount, $wallet, $activationTimestamp = 'now')
    {
        return $this->instantTransactionData($to, $amount, $wallet['id'], $wallet['keysPair']['public'], $wallet['keysPair']['private'], $activationTimestamp);
    }


    /**
     * Get income and outcome transactions lists for current wallet
     * @return array ['outcome'=>[['from'=>'string','to'=>'string','amount'=>'int','fromTimestamp'=>'int']]]
     * @throws InvalidMethodException
     * @throws ReturnException
     * @throws RpcCallException
     */
    public function getTransactions()
    {
        $transactionBlocks = $this->request('getTransactions');

        foreach ($transactionBlocks['income'] as &$transaction) {
            $transaction = json_decode($transaction['data'], true);
        }

        foreach ($transactionBlocks['outcome'] as &$transaction) {
            $transaction = json_decode($transaction['data'], true);
        }

        return $transactionBlocks;

    }

    /**
     * Get wallet id, balance and public key from tiny address or full address. Throws ReturnException if error
     * @param string $id
     * @return array ['id'=>'string', 'block'=> 'int', 'keysPair'=>['public'=>'string','private'=>'string'], 'balance'=>'double']
     * @throws ReturnException
     * @throws RpcCallException
     * @throws Exception
     */
    public function getWalletInfo($id)
    {
        return $this->request('getWalletInfo', [], '/' . urldecode($id));
    }

    /**
     * Gets tiny address for wallet array
     * @param array $wallet
     * @return string
     */
    public static function getTinyAddress($wallet)
    {
        return self::TINY_ADDRESS_PREFIX . $wallet['block'];
    }

    /**
     * Converts Mil to Ben amount
     * @param int $amount
     * @return float|int
     */
    public static function mil2Ben($amount)
    {
        return $amount / self::MIL_TO_BEN;
    }

    /**
     * Converts Ben to mil amount
     * @param float|int $amount
     * @return int
     */
    public static function ben2Mil($amount)
    {
        return round($amount * self::MIL_TO_BEN);
    }

}

class InvalidMethodException extends Exception
{
}

class RpcCallException extends Exception
{
}

class ReturnException extends Exception
{
}