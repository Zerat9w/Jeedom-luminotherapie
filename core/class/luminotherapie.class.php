<?php
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
class luminotherapie extends eqLogic {
	public static function deamon_info() {
		$return = array();
		$return['log'] = 'luminotherapie';
		$return['launchable'] = 'ok';
		$return['state'] = 'ok';
		
		return $return;
	}
	public static function deamon_start($_debug = false) {
		log::remove('luminotherapie');
		self::deamon_stop();
		$deamon_info = self::deamon_info();
		if ($deamon_info['launchable'] != 'ok') 
			return;
		if ($deamon_info['state'] == 'ok') 
			return;
		
	}
	public static function deamon_stop() {	
		
	}
	public function postSave() {
		$this->AddCommande('Démarrage','start',"action", 'other',1);
		$this->AddCommande('Arret','stop',"action", 'other',1);
		
	}
	public function AddCommande($Name,$_logicalId,$Type="info", $SubType='binary',$visible,$Template='') {
		$Commande = $this->getCmd(null,$_logicalId);
		if (!is_object($Commande))
		{
			$Commande = new luminotherapieCmd();
			$Commande->setId(null);
			$Commande->setName($Name);
			$Commande->setIsVisible($visible);
			$Commande->setLogicalId($_logicalId);
			$Commande->setEqLogic_id($this->getId());
			$Commande->setType($Type);
			$Commande->setSubType($SubType);
		}
     		$Commande->setTemplate('dashboard',$Template );
		$Commande->setTemplate('mobile', $Template);
		$Commande->save();
		return $Commande;
	}
	public function startSimulAubeDemon(){
		$cron = cron::byClassAndFunction('luminotherapie', 'SimulAubeDemon',array('id' => $this->getId()));
		if (!is_object($cron)) 
			$cron = new cron();
		$cron->setClass('luminotherapie');
		$cron->setFunction('SimulAubeDemon');
		$cron->setDeamon(1);
		$cron->setOption(array('id' => $this->getId()));
		$cron->setEnable(1);
		$cron->setSchedule('* * * * * *');
		$cron->save();
		return $cron;
	}
	public function removeSimulAubeDemon(){
		$cron = cron::byClassAndFunction('luminotherapie', 'SimulAubeDemon',array('id' => $this->getId()));
		if(is_object($cron)) {
			log::add('luminotherapie','debug','On termine le daemon de simulation');
			$cron->stop();
			$cron->remove();
		}
	}
	public static function SimulAubeDemon($_option){
		log::add('luminotherapie','debug','Exécution de l\'action réveil simulation d\'aube : '. json_encode($_option));
		$luminotherapie=eqLogic::byId($_option['id']);
		if(is_object($luminotherapie)){
			log::add('luminotherapie','debug','Simulation d\'aube : '.$luminotherapie->getHumanName());
			$time = 0;
			$cmd=cmd::byId(str_replace('#','',$luminotherapie->getConfiguration('DawnSimulatorCmd')));
			if(is_object($cmd)){
				while(true){
					$options['slider'] = ceil($luminotherapie->dawnSimulatorEngine(
						$luminotherapie->getConfiguration('DawnSimulatorEngineType'),
						$time,
						$luminotherapie->getConfiguration('DawnSimulatorEngineStartValue'), 
						$luminotherapie->getConfiguration('DawnSimulatorEngineEndValue'), 
						$luminotherapie->getConfiguration('DawnSimulatorEngineDuration')
					));
					log::add('luminotherapie','debug','Valeur de l\'intensité lumineuse : ' .$options['slider'].'/'.$luminotherapie->getConfiguration('DawnSimulatorEngineEndValue')." - durée : ".$time."/".$luminotherapie->getConfiguration('DawnSimulatorEngineDuration'));
					$time++;
					$cmd->Execute($options);
					if($options['slider'] == $luminotherapie->getConfiguration('DawnSimulatorEngineEndValue') || ($time - 1) == $luminotherapie->getConfiguration('DawnSimulatorEngineDuration')){
						log::add('luminotherapie','debug','Fin de la simulation d\'aube');
						$luminotherapie->removeSimulAubeDemon($_option);
						return;
					}else
						sleep(60);
				}
			}
		}
		
	}
	public function dawnSimulatorEngine($type, $time, $startValue, $endValue, $duration) {
		if($startValue=='')
			$startValue=0;
		if($endValue=='')
			$endValue=100;
		if($duration=='')
			$duration=30;
		switch ($type){
			case 'Linear':
				return $endValue * $time / $duration + $startValue;
			break;
			case 'InQuad':
				$time = $time / $duration;
				return $endValue * pow($time, 2) + $startValue;
			break;
			case 'InOutQuad':
				$time = $time / $duration * 2;
				if ($time < 1)
					return $endValue / 2 * pow($time, 2) + $startValue;
				else
					return -$endValue / 2 * (($time - 1) * ($time - 3) - 1) + $startValue;
			break;
			case 'InOutExpo':
				if ($time == 0)
					return $startValue ;
				if ($time == $duration)
					return $startValue + $endValue;
				$time = $time / $duration * 2;
				if ($time < 1)
					return $endValue / 2 * pow(2, 10 * ($time - 1)) + $startValue - $endValue * 0.0005;
				else{
					$time = $time - 1;
					return $endValue / 2 * 1.0005 * (-pow(2, -10 * $time) + 2) + $startValue;
				}
			break;
			case 'OutInExpo':
				if ($time < $duration / 2)
					return self::equations('OutExpo', $time * 2, $startValue, $endValue / 2, $duration);
				else
					return self::equations('InExpo', ($time * 2) - $duration, $startValue + $endValue / 2, $endValue / 2, $duration);
			break;
			case 'InExpo':
				if($time == 0)
					return $startValue;
				else
					return $endValue * pow(2, 10 * ($time / $duration - 1)) + $startValue - $endValue * 0.001;	
			break;
			case 'OutExpo':
				if($time == $duration)
					return $startValue + $endValue;
				else
					return $endValue * 1.001 * (-pow(2, -10 * $time / $duration) + 1) + $startValue;
			break;
		}
	}
	
}
class luminotherapieCmd extends cmd {
    public function execute($_options = null) {	
		switch($this->getLogicalId()){
			case 'start':
				$this->getEqLogic()->startSimulAubeDemon();
			break;
			case 'stop':
				$this->getEqLogic()->removeSimulAubeDemon();
			break;
				
		}	
	}
}
?>
