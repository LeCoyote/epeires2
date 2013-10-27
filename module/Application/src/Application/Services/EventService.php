<?php
namespace Application\Services;

use Zend\ServiceManager\ServiceManagerAwareInterface;
use Zend\ServiceManager\ServiceManager;

class EventService implements ServiceManagerAwareInterface{
	/**
	 * Service Manager
	 */
	protected $sm;
	
	/**
	 * Entity Manager
	 */
	private $em;
	
	public function setEntityManager(\Doctrine\ORM\EntityManager $em){
		$this->em = $em;
	}
	
	public function setServiceManager(ServiceManager $serviceManager){
		$this->sm = $serviceManager;
	}
	
	/**
	 * Get the name of an event depending on the title field of the category.
	 * If no title field is set, returns the event's id
	 * @param $event
	 */
	public function getName($event){	

		if($event instanceof \Application\Entity\PredefinedEvent){
			if($event->getParent() == null && $event->getName()){
				return $event->getName();
			}
		}
		
		$name = $event->getId();
		
		$category = $event->getCategory();
		
		$titlefield = $category->getFieldname();
		if($titlefield){
			foreach($event->getCustomFieldsValues() as $fieldvalue){
				if($fieldvalue->getCustomField()->getId() == $titlefield->getId()){
					$tempname = $this->sm->get('CustomFieldService')->getFormattedValue($fieldvalue->getCustomField(), $fieldvalue->getValue());
					
					if($tempname){
						$name = $tempname;
					}
				}
			}
		}
		return $name;
	}
		
	protected function sortbydate($a, $b){
		return \DateTime::createFromFormat(DATE_RFC2822, $a) > \DateTime::createFromFormat(DATE_RFC2822, $b);
	}
	
	/**
	 * Returns an array :
	 * datetime => array('date' => datetime object,
	 * 					 'changes' => array(array ('fieldname', 'oldvalue', 'newvalue', 'user'))
	 * 					)
	 * @param Application\Entity\Event $event
	 */
	public function getHistory($event){
		$history = array();

		$repo = $this->em->getRepository('Application\Entity\Log');
		
		//history of event
		$logentries = $repo->getLogEntries($event);	
		if(count($logentries) > 1 && $logentries[count($logentries)-1]->getAction() == "create" ){
			$ref = null;
			foreach (array_reverse($logentries) as $logentry){
				if(!$ref){ //set up reference == "create" entry
					$ref = $logentry->getData();
				} else {
					foreach($logentry->getData() as $key => $value){
						//sometimes log stores values that didn't changed
						if($ref[$key] != $value){
							if(!array_key_exists($logentry->getLoggedAt()->format(DATE_RFC2822), $history)){
								$entry = array();
								$entry['date'] = $logentry->getLoggedAt();
								$entry['changes'] = array();
								$history[$logentry->getLoggedAt()->format(DATE_RFC2822)] = $entry;
							}
							$historyentry = array();
							$historyentry['fieldname'] = $key;
							if($key == 'end_date' || $key == 'start_date'){
								//do it in UTC
								$offset = date("Z");
								
								if($ref[$key]){
									$oldvalue = clone $ref[$key];
									$oldvalue->setTimezone(new \DateTimeZone("UTC"));
									$oldvalue->add(new \DateInterval("PT".$offset."S"));
								}
								
								$newvalue = clone $value;
								$newvalue->setTimezone(new \DateTimeZone("UTC"));
								$newvalue->add(new \DateInterval("PT".$offset."S"));
								
								$historyentry['oldvalue'] = ($ref[$key] ? $oldvalue->format("d M Y, H:i T") : '');
								$historyentry['newvalue'] = $newvalue->format("d M Y, H:i T");
							} else if ($key == 'punctual') {
								$historyentry['oldvalue'] = ($ref[$key] ? "Vrai" : "Faux") ;
								$historyentry['newvalue'] = ($value ? "Vrai" : "Faux");
							} else {
								$historyentry['oldvalue'] = $ref[$key];
								$historyentry['newvalue'] = $value;
							}
							
							$history[$logentry->getLoggedAt()->format(DATE_RFC2822)]['changes'][] = $historyentry;
							//update ref
							$ref[$key] = $value;
						}
					}
				}
			}
		}
		
		//history of customfields
		foreach($this->em->getRepository('Application\Entity\CustomFieldValue')->findBy(array('event'=>$event->getId())) as $customfieldvalue){
			$fieldlogentries = $repo->getLogEntries($customfieldvalue);
			if(count($fieldlogentries) > 1 && $fieldlogentries[count($fieldlogentries)-1]->getAction() == "create"){
				$ref = null;
				foreach(array_reverse($fieldlogentries) as $fieldlogentry){
					if(!$ref){
						$ref = $fieldlogentry->getData();
					} else {
						foreach ($fieldlogentry->getData() as $key => $value){
							if($ref[$key] != $value){
								if(!array_key_exists($fieldlogentry->getLoggedAt()->format(DATE_RFC2822), $history)){
									$entry = array();
									$entry['date'] = $fieldlogentry->getLoggedAt();
									$entry['changes'] = array();
									$history[$fieldlogentry->getLoggedAt()->format(DATE_RFC2822)] = $entry;
								}
								$historyentry = array();
								$historyentry['fieldname'] = $customfieldvalue->getCustomField()->getName();
								$historyentry['oldvalue'] = $this->sm->get('CustomFieldService')->getFormattedValue($customfieldvalue->getCustomField(),$ref[$key]);
								$historyentry['newvalue'] = $this->sm->get('CustomFieldService')->getFormattedValue($customfieldvalue->getCustomField(),$value);
								$history[$fieldlogentry->getLoggedAt()->format(DATE_RFC2822)]['changes'][] = $historyentry;
								//update ref
								$ref[$key] = $value;
							}
						}
					}
				}
			}
		}
		
		//updates
		foreach($event->getUpdates() as $update){
			if(!array_key_exists($update->getCreatedOn()->format(DATE_RFC2822), $history)){
				$entry = array();
				$entry['date'] = $update->getCreatedOn();
				$entry['changes'] = array();
				$history[$update->getCreatedOn()->format(DATE_RFC2822)] = $entry;
			}
			$historyentry = array();
			$historyentry['fieldname'] = 'note';
			$historyentry['oldvalue'] = '';
			$historyentry['newvalue'] = $update->getText();
			$history[$update->getCreatedOn()->format(DATE_RFC2822)]['changes'][] = $historyentry;
		}
		
		uksort($history, array($this, "sortbydate"));
		
		return $history;
	}
	
}