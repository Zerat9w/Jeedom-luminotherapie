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
		
	}
	public static function AddCommande($eqLogic,$Name,$_logicalId,$Type="info", $SubType='binary',$visible,$Template='') {
		$Commande = $eqLogic->getCmd(null,$_logicalId);
		if (!is_object($Commande))
		{
			$Commande = new luminotherapieCmd();
			$Commande->setId(null);
			$Commande->setName($Name);
			$Commande->setIsVisible($visible);
			$Commande->setLogicalId($_logicalId);
			$Commande->setEqLogic_id($eqLogic->getId());
			$Commande->setType($Type);
			$Commande->setSubType($SubType);
		}
     		$Commande->setTemplate('dashboard',$Template );
		$Commande->setTemplate('mobile', $Template);
		$Commande->save();
		return $Commande;
	}
	public function removeSimulAubeDemon($_option){
		$cron = cron::byClassAndFunction('luminotherapie', 'SimulAubeDemon',$_option);
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
			$cmd=cmd::byId($_option['cmd']);
			if(is_object($cmd)){
				while(true){
					$options['slider'] = ceil($luminotherapie->dawnSimulatorEngine(
						$cmd['configuration']['DawnSimulatorEngineType'],
						$time,
						$cmd['configuration']['DawnSimulatorEngineStartValue'], 
						$cmd['configuration']['DawnSimulatorEngineEndValue'], 
						$cmd['configuration']['DawnSimulatorEngineDuration']
					));
					log::add('luminotherapie','debug','Valeur de l\'intensité lumineuse : ' .$options['slider'].'/'.$cmd['configuration']['DawnSimulatorEngineEndValue']." - durée : ".$time."/".$cmd['configuration']['DawnSimulatorEngineDuration']);
					$time++;
					$luminotherapie->ExecuteAction($cmd,$options);

					if($options['slider'] == $cmd['configuration']['DawnSimulatorEngineEndValue'] || ($time - 1) == $cmd['configuration']['DawnSimulatorEngineDuration']){
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
	public function ExecuteAction($cmd,$options='') {
		if (isset($cmd['enable']) && $cmd['enable'] == 0)
			return;
		try {
			$options = array();
			if (isset($cmd['options'])) 
				$options = $cmd['options'];
			scenarioExpression::createAndExec('action', $cmd['cmd'], $options);
		} catch (Exception $e) {
			log::add('Volets', 'error', __('Erreur lors de l\'éxecution de ', __FILE__) . $action['cmd'] . __('. Détails : ', __FILE__) . $e->getMessage());
		}
		$Commande=cmd::byId(str_replace('#','',$cmd['cmd']));
		if($options=='')
			$options=$cmd['options'];
		if(is_object($Commande)){
			log::add('luminotherapie','debug','Exécution de '.$Commande->getHumanName());
			$Commande->execute($options);
		}		
	}
	public function CreateCron($Schedule, $logicalId, $demon=false) {
		log::add('luminotherapie','debug','Création du cron "'.$logicalId.'" ID = '.$this->getId().' --> '.$Schedule);
		$cron = cron::byClassAndFunction('luminotherapie', $logicalId,array('id' => $this->getId()));
		if (!is_object($cron)) 
			$cron = new cron();
		$cron->setClass('luminotherapie');
		$cron->setFunction($logicalId);
		$options['id']= $this->getId();
		if($demon!= false){
			$options['cmd']= $demon->getId();
			$cron->setDeamon(1);
		}
		$cron->setOption($options);
		$cron->setEnable(1);
		$cron->setSchedule($Schedule);
		$cron->save();
		return $cron;
	}
	
}
class luminotherapieCmd extends cmd {
    public function execute($_options = null) {	
		
	}
}
?>
