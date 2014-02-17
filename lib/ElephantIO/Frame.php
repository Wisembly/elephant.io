<?php
namespace ElephantIO;
/**
 * Example of usage
 *
 * $client = new \ElephantIO\Client(...);
 *  ... your code ...
 *
 * $client->createFrame()->endPoint('/event')->emit('update', array(1,2,3));
 * $client->close();
 */
class Frame {

	/**
	 * @var
	 */
	public $id;

	/**
	 * @var string
	 */
	protected $_data;

	/**
	 * @var int
	 */
	protected $_type;

	/**
	 * @var string
	 */
	protected $_endPoint;

	/**
	 * @var Client
	 */
	private $_client;

	/**
	 * @param Client $client
	 */
	public function __construct(Client $client, $type = null, $endpoint = null) {
		$this->_client = $client;
		if ($type) {
			$this->setType($type);
		}
		if ($endpoint) {
			$this->endPoint($endpoint);
		}
	}

	/**
	 * @param int $type constant from Client
	 *
	 * @return Frame
	 * @throws \InvalidArgumentException
	 */
	public function setType($type) {
		if (!is_int($type) || $type > 8) {
			throw new \InvalidArgumentException('ElephantIOClient::send() type parameter must be an integer strictly inferior to 9.');
		}
		$this->_type = $type;
		return $this;
	}

	/**
	 * @param $endPoint
	 *
	 * @return Frame
	 */
	public function endPoint($endPoint) {
		if (is_string($endPoint)) {
			$this->_endPoint = $endPoint;
			$this->_client->of($endPoint);
		}
		return $this;
	}

	/**
	 * Send data to server as (3)Message if type is undefined
	 * You can send json messages, before do it set type Client::TYPE_JSON_MESSAGE
	 *
	 * @param mixed $data
	 *
	 * @return Frame
	 */
	public function send($data) {
		if (!$this->_type) {
			$this->_type = Client::TYPE_MESSAGE;
		} else if ($this->_type === Client::TYPE_JSON_MESSAGE && !is_string($data)) {
			$data = json_encode($data);
		}
		$this->_data = (string) $data;
		$this->_client->sendFrame($this);
		return $this;
	}

	public function emit($event, array $arguments) {
		$this->_type = Client::TYPE_EVENT;
		$this->_data = json_encode(array(
			'name' => (string) $event,
			'args' => $arguments
		));
		$this->_client->sendFrame($this);
		return $this;
	}

	/**
	 * @return string
	 */
	public function getData() {
		return $this->_data;
	}

	/**
	 * @return int
	 */
	public function getType() {
		return $this->_type;
	}

	/**
	 * @return mixed
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @return string
	 */
	public function getEndPoint() {
		return $this->_endPoint;
	}
}
