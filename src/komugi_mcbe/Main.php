<?php

declare(strict_types=1);

namespace komugi_mcbe;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

use komugi_mcbe\scheduler\CallbackTask;

class Main extends PluginBase implements Listener{//別にクラスわけなくてもいいかな...

	/** @var MySQL */
	public $mysql;

	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		if(!file_exists($this->getDataFolder())) mkdir($this->getDataFolder(), 0744, true);
		$this->config = new Config($this->getDataFolder() . "config.json", Config::JSON, [
			"mysql" => ""
		]);
		if(empty($this->config->get("mysql"))){
			$this->getLogger()->error("MySQLの情報をConfigに記述し、再度サーバーを起動してください");
			$this->getServer()->getPluginManager()->disablePlugin($this);
		}elseif(count(explode(";",$this->config->get("mysql"))) !== 4){
			$this->getLogger()->error("MySQLDataの記述方法に問題があります。再度確認しサーバーを起動してください。");
			$this->getServer()->getPluginManager()->disablePlugin($this);
		}else{
			$mysqlData = explode(";",$this->config->get("mysql"));

			$this->mysql = mysqli_connect($mysqlData[0], $mysqlData[1], $mysqlData[2], $mysqlData[3]);
			if(!mysqli_connect_errno()){
				$this->getLogger()->info("§aデータベースに接続しました");
			}

			$this->getScheduler()->scheduleRepeatingTask(new CallbackTask([$this,"getDiscordChat"],[]), 5);

			$this->getLogger()->info("§bSyncDiscordを読み込みました");
		}
	}

	public function onDisable(){
		if($this->mysql !== null){
			$this->mysql->query("TRUNCATE sync_discord");
		}
	}

	public function onChat(PlayerChatEvent $event) : void{
		$player = $event->getPlayer();
		$name = $player->getName();
		$msg = $event->getMessage();

		$this->mysql->query("INSERT INTO sync_discord(`name`,`chat`,`type`) VALUES ('$name','$msg','server')");
	}

	public function getDiscordChat() : void{
		$query = $this->mysql->query("SELECT * FROM sync_discord WHERE type='discord'");
		while($row = $query->fetch_assoc()){
			$this->getServer()->broadcastMessage("§b[Discord] §f<{$row["name"]}> {$row["chat"]}");
			$this->mysql->query("DELETE FROM sync_discord WHERE id='{$row["id"]}'");
		}
	}
}