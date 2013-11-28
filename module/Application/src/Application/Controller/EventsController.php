<?php
/**
 * Epeires 2
 * @license   https://www.gnu.org/licenses/agpl-3.0.html Affero Gnu Public License
 */

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Application\Entity\Event;
use Application\Form\EventForm;
use Application\Form\CategoryFormFieldset;
use Application\Form\CustomFieldset;
use Application\Entity\CustomFieldValue;
use Zend\View\Model\JsonModel;
use Doctrine\Common\Collections\Criteria;
use DoctrineModule\Stdlib\Hydrator\DoctrineObject;
use Zend\Form\Annotation\AnnotationBuilder;
use Zend\Form\Fieldset;
use Zend\Form\Element\File;
use Zend\Form\Element\Text;
use Application\Form\FileFieldset;
use Application\Services\EventService;

class EventsController extends FormController {
	
    public function indexAction(){    	
    	
    	$viewmodel = new ViewModel();
    	
    	$return = array();
    	
    	if($this->flashMessenger()->hasErrorMessages()){
    		$return['errorMessages'] =  $this->flashMessenger()->getErrorMessages();
    	}
    	
    	if($this->flashMessenger()->hasSuccessMessages()){
    		$return['successMessages'] =  $this->flashMessenger()->getSuccessMessages();
    	}
    	
    	$this->flashMessenger()->clearMessages();
    	
    	$this->layout()->cds = "Nom chef de salle";
    	$this->layout()->ipo = "Nom IPO (téléphone)";
    	
    	
     	$viewmodel->setVariables(array('messages'=>$return));
    	 
        return $viewmodel;
    }
    
 	/**
 	 * 
 	 * @return \Zend\View\Model\JsonModel Exception : if query param 'return' is true, redirect to route application. 
 	 */
    public function saveAction(){   
    	
		$messages = array();
		$event = null;
		$return = $this->params()->fromQuery('return', null);
		
    	if($this->zfcUserAuthentication()->hasIdentity()){
    		
    		if($this->getRequest()->isPost()){
    			$post = array_merge_recursive($this->getRequest()->getPost()->toArray(),
    									$this->getRequest()->getFiles()->toArray());
    			$id = $post['id'] ? $post['id'] : null;
    		  		
    			$objectManager = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager');
    			
    			$credentials = false;
    			
    			if($id){
    				//modification
    				$event = $objectManager->getRepository('Application\Entity\Event')->find($id);
    				if($event){
    					if($this->isGranted('events.write') || $event->getAuthor()->getId() === $this->zfcUserAuthentication()->getIdentity()->getId()){
							$credentials = true;
    					}
    				}
    			} else {
    				//création
    				if($this->isGranted('events.create')){
    					$event = new Event();
    					$event->setAuthor($this->zfcUserAuthentication()->getIdentity());
    					$credentials = true;
    				}
    			}
    			
    			if($credentials){
    				
    				$form = $this->getSkeletonForm($event);
    					
    				$form->setData($post);
    				 
    				if($form->isValid()){
    						
    					//TODO find why hydrator can't set a null value to a datetime
    					if(isset($post['enddate']) && empty($post['enddate'])){
    						$event->setEndDate(null);
    					}
    					 
    					//hydrator can't guess timezone, force UTC of end and start dates
    					if(isset($post['startdate']) && !empty($post['startdate'])){
    						$offset = date("Z");
    						$startdate = new \DateTime($post['startdate']);
    						$startdate->setTimezone(new \DateTimeZone("UTC"));
    						$startdate->add(new \DateInterval("PT".$offset."S"));
    						$event->setStartdate($startdate);
    					}
    					if(isset($post['enddate']) && !empty($post['enddate'])){
    						$offset = date("Z");
    						$enddate = new \DateTime($post['enddate']);
    						$enddate->setTimezone(new \DateTimeZone("UTC"));
    						$enddate->add(new \DateInterval("PT".$offset."S"));
    						$event->setEnddate($enddate);
    					}
    					 
    					//save optional datas
    					if(isset($post['custom_fields'])){
    						foreach ($post['custom_fields'] as $key => $value){
    							//génération des customvalues si un customfield dont le nom est $key est trouvé
    							$customfield = $objectManager->getRepository('Application\Entity\CustomField')->findOneBy(array('id'=>$key));
    							if($customfield){
    								$customvalue = $objectManager->getRepository('Application\Entity\CustomFieldValue')
    								->findOneBy(array('customfield'=>$customfield->getId(), 'event'=>$id));
    								if(!$customvalue){
    									$customvalue = new CustomFieldValue();
    									$customvalue->setEvent($event);
    									$customvalue->setCustomField($customfield);
    									$event->addCustomFieldValue($customvalue);
    								}
    								$customvalue->setValue($value);
    								$objectManager->persist($customvalue);
    							}
    						}
    					}
    					//create associated actions (only relevant if creation from a model
    					if(isset($post['modelid'])){
    						$parentID = $post['modelid'];
    						//get actions
    						foreach ($objectManager->getRepository('Application\Entity\PredefinedEvent')->findBy(array('parent'=>$parentID)) as $action){
    							$child = new Event();
    							$child->setParent($event);
    							$child->createFromPredefinedEvent($action);
    							$child->setStatus($objectManager->getRepository('Application\Entity\Status')->findOneBy(array('defaut'=>true, 'open'=> true)));
    							//customfields
    							foreach($action->getCustomFieldsValues() as $customvalue){
    								$newcustomvalue = new CustomFieldValue();
    								$newcustomvalue->setEvent($child);
    								$newcustomvalue->setCustomField($customvalue->getCustomField());
    								$newcustomvalue->setValue($customvalue->getValue());
    								$objectManager->persist($newcustomvalue);
    							}
    							$objectManager->persist($child);
    						}
    					}
    					 
    					//fichiers
    					if(isset($post['fichiers']) && is_array($post['fichiers'])){
    						foreach ($post['fichiers'] as $key => $f){
    								
    							$count = substr($key, strlen($key) -1);
    								
    							if(!empty($f['file'.$count]['name'])){
    									
    								$file = new \Application\Entity\File($f['file'.$count],
    										$objectManager->getRepository('Application\Entity\File')->findBy(array('filename'=>$f['file'.$count]['name'])));
    								if(isset($f['name'.$count]) && !empty($f['name'.$count])){
    									$file->setName($f['name'.$count]);
    								}
    								if(isset($f['reference'.$count]) && !empty($f['reference'.$count])){
    									$file->setReference($f['reference'.$count]);
    								}
    								$file->addEvent($event);
    								$objectManager->persist($file);
    							}
    						}
    					}
    					 
    					
    					$objectManager->persist($event);
    					$objectManager->flush();
    					
    					$messages['success'][] = ($id ? "Evènement modifié" : "Évènement enregistré");
    					
    				} else {
    					//formulaire invalide
    					$messages['error'][] = "Impossible d'enregistrer l'évènement.";
    					//traitement des erreurs de validation
    					$this->processFormMessages($form->getMessages(), $messages);
    				}
    					
    			} else {
    				$messages['error'][] = "Création ou modification impossible, droits insuffisants.";
    			}
    			
    		} else {
    			$messages['error'][] = "Requête illégale.";
    		}
    		
    	} else {
    		$messages['error'][] = "Utilisateur non authentifié, action impossible.";
    	}
    	
    	if($return){
    		foreach ($messages['success'] as $message){
    			$this->flashMessenger()->addSuccessMessage($message);
    		}
    		foreach ($messages['error'] as $message){
    			$this->flashMessenger()->addErrorMessage($message);
    		}
    		return $this->redirect()->toRoute('application');
    	} else {
    		$json = array();
    		$json['messages'] = $messages;
    		if($event){
    			$json['events'] = array($event->getId() => $this->getEventJson($event));
    		}
    		return new JsonModel($json);
    	}
    	
    }
    
    public function subformAction(){
    	$part = $this->params()->fromQuery('part', null);
    	
    	$viewmodel = new ViewModel();
    	$request = $this->getRequest();
    	 
    	//disable layout if request by Ajax
    	$viewmodel->setTerminal($request->isXmlHttpRequest());
    	 
    	$em = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager');
    	 
    	$form = $this->getSkeletonForm();
    	
    	if($part){
    		switch ($part) {
    			case 'subcategories':
    				$id = $this->params()->fromQuery('id');
    				$subcat = $this->filterReadableCategories($em->getRepository('Application\Entity\Category')->getChilds($id));
    				$subcatarray = array();
    				foreach ($subcat as $cat){
    					$subcatarray[$cat->getId()] = $cat->getName();
    				}
    				$viewmodel->setVariables(array(
    						'part' => $part,
    						'values' => $subcatarray,
    				));
    				break;
    			case 'predefined_events':
    				$id = $this->params()->fromQuery('id');
    				$em = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager');
    				$category = $em->getRepository('Application\Entity\Category')->find($id);
    				$viewmodel->setVariables(array(
    						'part' => $part,
    						'values' => $em->getRepository('Application\Entity\PredefinedEvent')->getEventsWithCategoryAsArray($category),
    				));
    				break;
    			case 'custom_fields':
    				$viewmodel->setVariables(array(
    				'part' => $part,));
    				$form->add(new CustomFieldset($this->getServiceLocator(), $this->params()->fromQuery('id')));
    				break;
    			case 'file_field':
    				$count = $this->params()->fromQuery('count', 1);
    				$viewmodel->setVariables(array(
    					'part' => $part,
    					'count' => $count,
    				));
    				$form->get('fichiers')->addFile($count);
    				break;
    			default:
    				;
    				break;
    		}
    	}
    	$viewmodel->setVariables(array('form' => $form));
    	return $viewmodel;
    }
    
    /**
     * Create a new form
     * @return \Zend\View\Model\ViewModel
     */
    public function formAction(){
    	
    	$viewmodel = new ViewModel();
    	$request = $this->getRequest();
    	
    	//disable layout if request by Ajax    	
    	$viewmodel->setTerminal($request->isXmlHttpRequest());
    	  	
    	$em = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager');
    	
    	//création du formulaire : identique en cas de modif ou création
    	$form = $this->getSkeletonForm();
    	 
    	$id = $this->params()->fromQuery('id', null);
    	if($id){ //modification, prefill form
    		try {
    			$event = $em->getRepository('Application\Entity\Event')->find($id);
    		}
    		catch (\Exception $ex) {
    			$viewmodel->setVariables(array('error' => "Impossible de modifier l'évènement."));
    			return $viewmodel;
    		}
    		if(!$event){
    			$viewmodel->setVariables(array('error' => "Impossible de trouver l'évènement demandé."));
    			return $viewmodel;
    		}
    		$cat = $event->getCategory();
    		if($cat && $cat->getParent()){
    			$form->get('categories')->get('subcategories')->setValueOptions(
    					$em->getRepository('Application\Entity\Category')->getChildsAsArray($cat->getParent()->getId()));
    			$form->get('categories')->get('root_categories')->setAttribute('value', $cat->getParent()->getId());
    			$form->get('categories')->get('subcategories')->setAttribute('value', $cat->getId());
    		} else {
    			$form->get('categories')->get('root_categories')->setAttribute('value', $cat->getId());
    		}
    		//custom fields
    		$form->add(new CustomFieldset($this->getServiceLocator(), $cat->getId()));
    		//custom fields values
    		foreach ($em->getRepository('Application\Entity\CustomField')->findBy(array('category'=>$cat->getId())) as $customfield){
    			$customfieldvalue = $em->getRepository('Application\Entity\CustomFieldValue')->findOneBy(array('event'=>$event->getId(), 'customfield'=>$customfield->getId()));
    			if($customfieldvalue){
    				$form->get('custom_fields')->get($customfield->getId())->setAttribute('value', $customfieldvalue->getValue());
    			}
    		}
    		
    		//other values
    		$form->bind($event);
    		$form->setData($event->getArrayCopy());
    		$viewmodel->setVariables(array('event'=>$event));
    	}
    	
    	$viewmodel->setVariables(array('form' => $form));
    	return $viewmodel;
    	 
    }
    
    private function getSkeletonForm($event = null){
    	$em = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager');
    	
    	if(!$event){
    		$event = new Event();
    	}
    	
    	$builder = new AnnotationBuilder();
    	$form = $builder->createForm($event);
    	$form->setHydrator(new DoctrineObject($em, 'Application\Entity\Event'))
    		->setObject($event);    	
    	
    	$form->get('status')
    		->setValueOptions($em->getRepository('Application\Entity\Status')->getAllAsArray());
    	
    	$form->get('impact')
    		->setValueOptions($em->getRepository('Application\Entity\Impact')->getAllAsArray());

    	//add default fieldsets
    	$rootCategories = $this->filterReadableCategories($em->getRepository('Application\Entity\Category')->getRoots(null, true));
    	$rootarray = array();
    	foreach ($rootCategories as $cat){
    		$rootarray[$cat->getId()] = $cat->getName();
    	}
    	
    	$form->add(new CategoryFormFieldset($rootarray));
 	    	
    	//files
    	$filesFieldset = new FileFieldset('fichiers');
    	$filesFieldset->addFile();
    	
    	$form->add($filesFieldset);
    	$form->bind($event);
    	$form->setData($event->getArrayCopy());
    	
    	$form->add(array(
    			'name' => 'submit',
    			'attributes' => array(
    					'type' => 'submit',
    					'value' => 'Ajouter',
    					'class' => 'btn btn-primary',
    			),
    	));
    	
    	return $form;
    }
    
    public function getpredefinedvaluesAction(){
    	$predefinedId = $this->params()->fromQuery('id',null);
    	$json = array();
    	$defaultvalues = array();
    	$customvalues = array();
    	
    	$objectManager = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager');
    	$entityService = $this->getServiceLocator()->get('EventService');
    	
    	$predefinedEvt = $objectManager->getRepository('Application\Entity\PredefinedEvent')->find($predefinedId);
    	
    	$defaultvalues['punctual'] = $predefinedEvt->isPunctual();
		//TODO Impact
    	$json['defaultvalues'] = $defaultvalues;
    	
    	foreach ($predefinedEvt->getCustomFieldsValues() as $customfieldvalue){
    		$customvalues[$customfieldvalue->getCustomField()->getId()] = $customfieldvalue->getValue();
    	}
    	
    	$json['customvalues'] = $customvalues;
    	
    	return new JsonModel($json);
    }
    
    public function getactionsAction(){
    	$parentId = $this->params()->fromQuery('id', null);
    	$json = array();
    	$objectManager = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager');
    	
    	foreach ($objectManager->getRepository('Application\Entity\PredefinedEvent')->findBy(array('parent' => $parentId), array('place' => 'DESC')) as $action){
    		$json[$action->getId()] = array('name' =>  $this->getServiceLocator()->get('EventService')->getName($action),
    										'impactname' => $action->getImpact()->getName(),
    										'impactstyle' => $action->getImpact()->getStyle());
    	}
    	
    	return new JsonModel($json);
    }
    
    /**
     * Return {'open' => '<true or false>'}
     * @return \Zend\View\Model\JsonModel
     */
    public function toggleficheAction(){
    	$evtId = $this->params()->fromQuery('id', null);
    	$json = array();
    	$objectManager = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager');
    	
    	$event = $objectManager->getRepository('Application\Entity\Event')->find($evtId);
    	
    	if($event){
    		$event->setStatus($objectManager->getRepository('Application\Entity\Status')->findOneBy(array('defaut'=>true, 
    																									'open' => !$event->getStatus()->isOpen())));
    		$objectManager->persist($event);
    		$objectManager->flush();
    	}
    	
    	$json['open'] = $event->getStatus()->isOpen();
    	    	
    	return new JsonModel($json);
    }
    
    /**
     * {'evt_id_0' => {
     * 		'name' => evt_name,
     * 		'modifiable' => boolean,
     * 		'start_date' => evt_start_date,
     *		'end_date' => evt_end_date,
     *		'punctual' => boolean,
     *		'category' => evt_category_name,
     *		'category_short' => evt_category_short_name,
     *		'status_name' => evt_status_name,
     *		'actions' => {
     *			'action_name0' => open? (boolean),
     *			'action_name1' => open? (boolean),
     *			...
     *			}
     * 		},
     * 	'evt_id_1' => ...
     * }
     * @return \Zend\View\Model\JsonModel
     */
    public function geteventsAction(){
    	
    	$lastmodified = $this->params()->fromQuery('lastmodified', null);
    	
    	$objectManager = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager');
    	
    	$criteria = Criteria::create();
    	if($lastmodified){
    		$criteria->andWhere(Criteria::expr()->gte('last_modified_on', $lastmodified));
    	} else {
    		//every open events and all events of the last 3 days
    		$now = new \DateTime('NOW');
    		$criteria->andWhere(Criteria::expr()->gte('startdate', $now->sub(new \DateInterval('P3D'))));
    		$criteria->orWhere(Criteria::expr()->in('status', array(1,2)));
    	}

    	$events = $objectManager->getRepository('Application\Entity\Event')->matching($criteria);
    	
    	$readableEvents = array();
    	
    	if($this->zfcUserAuthentication()->hasIdentity()){
    		$roles = $this->zfcUserAuthentication()->getIdentity()->getRoles();
    		foreach ($events as $event){
    			$eventroles = $event->getCategory()->getReadroles();
    			foreach ($roles as $role){
    				if($eventroles->contains($role)){
    					$readableEvents[] = $event;
    					break;
    				}
    			}
    		}
    	} else {
    		$role = $this->getServiceLocator()->get('Core\Service\Rbac')->getOptions()->getAnonymousRole();
    		$roleentity = $objectManager->getRepository('Core\Entity\Role')->findOneBy(array('name'=>$role));
    		if($roleentity){
    			foreach ($events as $event){
    				$eventroles = $event->getCategory()->getReadroles();
    				if($eventroles->contains($roleentity)){
    					$readableEvents[] = $event;
    				}
    			}
    		}
    	}
    	
    	$json = array();
    	foreach ($readableEvents as $event){ 		
    		$json[$event->getId()] = $this->getEventJson($event);
    	}
    	
    	return new JsonModel($json);
    }
    
    private function getEventJson($event){
    	$eventservice = $this->getServiceLocator()->get('EventService');
    	$json = array('name' => $eventservice->getName($event),
    					'modifiable' => $eventservice->isModifiable($event),
    					'start_date' => $event->getStartdate()->format(DATE_RFC2822),
    					'end_date' => ($event->getEnddate() ? $event->getEnddate()->format(DATE_RFC2822) : null),
    					'punctual' => $event->isPunctual(),
    					'category_root' => ($event->getCategory()->getParent() ? $event->getCategory()->getParent()->getName() : $event->getCategory()->getName()),
    					'category_root_short' => ($event->getCategory()->getParent() ? $event->getCategory()->getParent()->getShortName() : $event->getCategory()->getShortName()),
    					'category' => $event->getCategory()->getName(),
    					'category_short' => $event->getCategory()->getShortName(),
    					'category_compact' => $event->getCategory()->isCompactMode(),
    					'status_name' => $event->getStatus()->getName(),
    					'impact_value' => $event->getImpact()->getValue(),
    					'impact_name' => $event->getImpact()->getName(),
    					'impact_style' => $event->getImpact()->getStyle(),
    	);
    	
    	$actions = array();
    	foreach ($event->getChilds() as $child){
    		$actions[$eventservice->getName($child)] = $child->getStatus()->isOpen();
    	}
    	$json['actions'] = $actions;
    	
    	return $json;
    }
    
    /**
     * Liste des catégories racines visibles timeline
     * Au format JSON
     */
    public function getcategoriesAction(){
    	$objectManager = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager');
    	$json = array();
    	$criteria = Criteria::create()->andWhere(Criteria::expr()->isNull('parent'));
    	$criteria->andWhere(Criteria::expr()->eq('timeline', true));
    	$categories = $objectManager->getRepository('Application\Entity\Category')->matching($criteria);
    	$readablecat = $this->filterReadableCategories($categories);
    	foreach ($readablecat as $category){
    		$json[$category->getId()] = array(
    			'name' => $category->getName(),
    			'short_name' => $category->getShortName(),
    			'color' => $category->getColor(),
    		);
    	}
    	
    	return new JsonModel($json);
    }
    
    private function filterReadableCategories($categories){
    	$readablecat = array();
    	foreach ($categories as $category){
    		if($this->zfcUserAuthentication()->hasIdentity()){
    			$roles = $this->zfcUserAuthentication()->getIdentity()->getRoles();
    			foreach ($roles as $role){
    				if($category->getReadroles(true)->contains($role)){
    					$readablecat[] = $category;
    					break;
    				}
    			}
    		} else {
    			$role = $this->getServiceLocator()->get('Core\Service\Rbac')->getOptions()->getAnonymousRole();
    			$roleentity = $objectManager->getRepository('Core\Entity\Role')->findOneBy(array('name'=>$role));
    			if($roleentity){
    				if($category->getReadroles(true)->contains($roleentity)){
    					$readablecat[] = $category;
    				}
    			}
    		}
    	
    	}
    	return $readablecat;
    }
    
    /**
     * Liste des impacts au format JSON
     */
    public function getimpactsAction(){
    	$objectManager = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager');
    	$json = array();
    	$impacts = $objectManager->getRepository('Application\Entity\Impact')->findAll();
    	foreach ($impacts as $impact){
    		$json[$impact->getId()] = array(
    				'name' => $impact->getName(),
    				'style' => $impact->getStyle(),
    				'value' => $impact->getValue(),
    		);
    	}
    	return new JsonModel($json);
    }
    
    public function gethistoryAction(){

    	$viewmodel = new ViewModel();
    	$request = $this->getRequest();
    	 
    	//disable layout if request by Ajax
    	$viewmodel->setTerminal($request->isXmlHttpRequest());
    	
    	$evtId = $this->params()->fromQuery('id', null);
    	
    	$eventservice = $this->getServiceLocator()->get('EventService');
    	$objectManager = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager');
    	
    	$event = $objectManager->getRepository('Application\Entity\Event')->find($evtId);
    	
    	$history = null;
    	if($event){
    		$history = $eventservice->getHistory($event);
    	}
    	
    	$viewmodel->setVariable('history', $history);
    	
    	return $viewmodel;
    }
    
    /**
     * Usage :
     * $this->url('application', array('controller' => 'events'))+'/changefield?id=<id>&field=<field>&value=<newvalue>'
     * @return JSon with messages
     */
    public function changefieldAction(){
    	$id = $this->params()->fromQuery('id', 0);
    	$field = $this->params()->fromQuery('field', 0);
    	$value = $this->params()->fromQuery('value', 0);
    	$messages = array();
    	if($id){
    		$objectManager = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager');
    		$event = $objectManager->getRepository('Application\Entity\Event')->find($id);
    		if ($event) {
				switch ($field) {
					case 'enddate' :
						$event->setEnddate(new \DateTime($value));
						$objectManager->persist($event);
						$objectManager->flush();
						$messages['success'][0] = "Date et heure de fin modifiées.";
						break;	
					case 'startdate' :
						$event->setStartdate(new \DateTime($value));
						$objectManager->persist($event);
						$objectManager->flush();
						$messages['success'][0] = "Date et heure de début modifiées.";
						break;
					case 'impact' :
						$impact = $objectManager->getRepository('Application\Entity\Impact')->findOneBy(array('value'=>$value));
						if($impact){
							$event->setImpact($impact);
							$objectManager->persist($event);
							$objectManager->flush();
							$messages['success'][0] = "Impact modifié.";
						}
						break;
					case 'star' :
						$event->setStar($value);
						$objectManager->persist($event);
						$objectManager->flush();
						$messages['success'][0] = "Evènement modifié.";
						break;
					case "status" :
						$status = $objectManager->getRepository('Application\Entity\Status')->findOneBy(array('name'=>$value));
						if($status){
							$event->setStatus($status);
							$objectManager->persist($event);
							$objectManager->flush();
							$messages['success'][0] = "Statut de l'évènement modifié.";
						}
					default :
						;
						break;
				}
    		}
    	} else {
    		$messages['error'][0] = "Impossible de modifier l'évènement.";
    	}
    	return new JsonModel($messages);
    }
    
}
