<?php
/**
 * @package Bitstamp API
 * @author https://bxmediaus.com - BX MEDIA - PHP + Bitcoin. We are ready to work on your next bitcoin project. Only high quality coding. https://bxmediaus.com
 * @version 0.1
 * @access public
 * @license http://www.opensource.org/licenses/LGPL-3.0
 */
class Bitstamp
{
	private $key;
	private $secret;
	private $client_id;
	private $version;
	public $redeemd;  // Redeemed code information
	public $withdrew; // Withdrawal information
	public $info;     // Result from getInfo()
	public $ticker;   // Current ticker (getTicker())
	public $eurusd;   // Current eur/usd

	/**
	 * Bitstamp::__construct()
	 * Sets required key and secret to allow the script to function
	 * @param Bitstamp API Key $key
	 * @param Bitstamp Secret $secret
	 * @return
	 */


	public function __construct($version, $key, $secret, $client_id)
	{
		if (isset($secret) && isset($key) && isset($client_id))
		{
			$this->key = $key;
			$this->version = $version;
			$this->secret = $secret;
			$this->client_id = $client_id;
		} else
			die("NO KEY/SECRET/CLIENT ID");
	}
	/**
	 * Bitstamp::bitstamp_query()
	 *
	 * @param API Path $path
	 * @param POST Data $req
	 * @return Array containing data returned from the API path
	 */
	public function bitstamp_query($path, array $req = array(), $verb = 'post')
	{
		$save = new Save;

		// API settings
		$key = $this->key;
		$version = $this->version;

		// echo '<pre>'.print_r($version,true).'</pre>';
 		// exit;

		// generate a nonce as microtime, with as-string handling to avoid problems with 32bits systems
		$mt = explode(' ', microtime());
		$req['nonce'] = $mt[1] . substr($mt[0], 2, 6);
		$req['key'] = $key;
		$req['signature'] = $this->get_signature($req['nonce']);


		// generate the POST data string
        $post_data = http_build_query($req, '', '&');
		// any extra headers
		$headers = array();

		// our curl handle (initialize if required)
		static $ch = null;
		if (is_null($ch))
		{
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_USERAGENT,
				'Mozilla/4.0 (compatible; PHP Client; ' . php_uname('s') . '; PHP/' .
				phpversion() . ')');
		}

		include (Yii::app()->params['libsPath']."/http-proxy.php");

		curl_setopt($ch, CURLOPT_URL, 'https://www.bitstamp.net/api/' . $version . $path .'/');
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);  // man-in-the-middle defense by verifying ssl cert.
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);  // man-in-the-middle defense by verifying ssl cert.

        if ($verb == 'post')
        {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        }
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		// run the query
		$res = curl_exec($ch);

		// echo '<pre>'.print_r($res,true).'</pre>';
 		// exit;

		if ($res === false){
	        $save->WriteLog('libs','exchanges','bitstamp_query','Could not get reply: ' . curl_error($ch), true);
		}

		$dec = json_decode($res, true);
		if (is_null($dec)){
			$save->WriteLog('libs','exchanges','bitstamp_query','Invalid data received, please make sure connection is working and requested API exists', true);
		}
		return $dec;
	}

	/**
	 * Bitstamp::ticker()
	 * Returns current ticker from Bitstamp
	 * @return $ticker
	 */
	function ticker() {
		$ticker = $this->bitstamp_query('ticker', array(), 'get');
		$this->ticker = $ticker; // Another variable to contain it.
		return $ticker;
	}


	/**
	 * Bitstamp::eurusd()
	 * Returns current EUR/USD rate from Bitstamp
	 * @return $eurusd
	 */
	function eurusd() {
		$eurusd = $this->bitstamp_query('eur_usd', array(), 'get');
		$this->eurusd = $eurusd; // Another variable to contain it.
		return $eurusd;
	}
	/**
	* Bitstamp::buyBTC()
	*
	* @param float $amount
	* @param float $price
	*/
	function buyBTC($amount, $price=NULL){
		if(is_null($price)){
			if (!isset($this->ticker))
				$this->ticker();
			$price = $this->ticker['ask'];
		}

		return $this->bitstamp_query('buy', array('amount' => $amount, 'price' => $price));
	}
	/**
	* Bitstamp::sellBTC()
	*
	* @param float $amount
	* @param float $price
	*/
	function sellBTC($amount, $price=NULL){
		if(is_null($price)){
			if (!isset($this->ticker))
				$this->ticker();
			$price = $this->ticker['bid'];
		}

		return $this->bitstamp_query('sell', array('amount' => $amount, 'price' => $price));
	}
	/**
	* Bitstamp::newOrder()
	*
	* @param float $amount
	* @param float $price
	* @param string $pair
	* @param string $type  (bid/ask)
	*/
	function newOrder($amount, $price=NULL, $pair='btcusd', $type){
		$side = 'ask';
		if ($type == 'sell'){
			$side = 'bid';
		}

		if(is_null($price)){
			if (!isset($this->ticker))
				$this->ticker();
			$price = $this->ticker[$side];
		}
		return $this->bitstamp_query($type.'/'.$pair, array('amount' => $amount, 'price' => $price));
	}
	/**
	* Bitstamp::transactions()
	* @param string $time
	* Utilizzare versione 1
	*/
	function transactions($time='hour'){
		return $this->bitstamp_query('transactions', array('time' => $time), 'get');
	}
	/**
	* Bitstamp::userTransactions()
	* @param string $time
	* Utilizzare versione 2
	*/
	function userTransactions($pair='btcusd',$limit=10){
		return $this->bitstamp_query('user_transactions/'.$pair, array('limit' => $limit));
	}


	/**
	* Bitstamp::orderBook()
	*
	* @param int $group
	*/
	function orderBook($group=1){
		return $this->bitstamp_query('order_book', array('group' => $group), 'get');
	}
	/**
	* Bitstamp::openOrders()
	* List of open orders
	*/
	function openOrders(){
		return $this->bitstamp_query('open_orders/all');
	}
	/**
	* Bitstamp::cancelOrder()
	*
	* @param int $id
	*/
	function cancelOrder($id=NULL){
		if(is_null($id))
			throw new Exception('Order id is undefined');
		return $this->bitstamp_query('cancel_order', array('id' => $id));
	}
	/**
	* Bitstamp::balance()
	*
	*/
	function balance(){
		$balance = $this->bitstamp_query('balance');
		$this->balance = $balance; // Another variable to contain it.
		return $balance;
	}
	/**
	* Bitstamp::unconfirmedbtc()
	*
	*/
	function unconfirmedbtc(){
		$unconfirmedbtc = $this->bitstamp_query('unconfirmed_btc');
		$this->unconfirmedbtc = $unconfirmedbtc; // Another variable to contain it.
		return $unconfirmedbtc;
	}

	/**
	* Bitstamp::bitcoindepositaddress()
	*
	*/
	function bitcoindepositaddress(){
		$bitcoindepositaddress = $this->bitstamp_query('bitcoin_deposit_address');
		$this->bitcoindepositaddress = $bitcoindepositaddress; // Another variable to contain it.
		return $bitcoindepositaddress;
	}
	/**
	* Bitstamp::get_signature()
	* Compute bitstamp signature
	* @param float $nonce
	*/
	private function get_signature($nonce)
	{

	  $message = $nonce.$this->client_id.$this->key;

	  return strtoupper(hash_hmac('sha256', $message, $this->secret));

	}

	/**
	* NUOVE FUNZIONI AGGIUNTE ! BY SERGIO CASIZZONE
	*/

	/**
	 * Bitstamp::tickerV2()
	 * Returns current ticker from Bitstamp
	 * @return $ticker
	 */
	function tickerV2($currency_pair) {
		$ticker = $this->bitstamp_query('ticker/'.$currency_pair, array(), 'get');
		$this->ticker = $ticker; // Another variable to contain it.
		return $ticker;
	}

	/**
	* Bitstamp::withdrawal()
	* Esegue una richiesta di prelievo bitcoin
	* @param string $coin
	* @param float $amount
	* @param string $address
	* @param string $instant  0 (false) or 1 (true). If the address supports BitGo and you need instant delivery of Bitcoins with zero confirmations.
	* Btc utilizza versione 1.
	*/
	function withdraw($asset, $address, $amount, $addressTag = false)
	{
		if ($asset == 'btc')
		 	$asset = 'bitcoin';

		return $this->bitstamp_query($asset.'_withdrawal', ['amount' => $amount, 'address' => $address, 'instant'=>0]);

	}


	/**
	* Bitstamp::withdrawal_requests()
	* List of Withdrawal Requests
	* @return Array containing List of Withdrawal Requests
	* API 2
	*/
	function withdrawal_requests()
	{
		return $this->bitstamp_query('withdrawal-requests', array());
	}

}
