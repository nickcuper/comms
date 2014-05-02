<?php

	namespace Concerto\Comms;
	use Evenement\EventEmitter;
	use React\EventLoop\LoopInterface;

	class Client extends EventEmitter {
		/**
		 * Address of the socket.
		 */
		protected $address;

		protected $loop;
		protected $server;

		public function __construct(LoopInterface $loop) {
			$this->loop = $loop;
		}

		public function close() {
			if ($this->hasServer() === false) return;

			$this->loop->nextTick(function() {
				$this->server->close();
			});
		}

		public function getAddress() {
			return $this->address;
		}

		public function hasServer() {
			return ($this->server instanceof Connection);
		}

		public function listen($address) {
			$this->address = $address;
			$client = stream_socket_client($address);
			stream_set_read_buffer($client, 0);
			stream_set_write_buffer($client, 0);

			$this->server = new Connection($client, $this->loop);

			$this->server->on('close', function() {
				$this->server = null;
				$this->emit('part');
				$this->emit('parted');
			});

			$this->server->on('data', function($data) {
				$transport = Transport::unpack($data);

				$this->emit('message', [$transport->getData(), $transport]);
			});

			$this->emit('join');
			$this->emit('joined');
		}

		public function send($message) {
			if ($this->hasServer() === false) return false;

			$data = Transport::pack($message);

			return $this->server->write("{$data}\n");
		}
	}