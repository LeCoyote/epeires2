<?php
/** Epeires 2
*
* @copyright Copyright (c) 2013 Bruno Spyckerelle
* @license   https://www.gnu.org/licenses/agpl-3.0.html Affero Gnu Public License
*/
namespace Application\Entity;

use Doctrine\ORM\Mapping as ORM;
use Zend\Form\Annotation;
/**
 * @ORM\Entity(repositoryClass="Application\Repository\ExtendedRepository")
 * @ORM\Table(name="frequencies")
 **/
class Frequency {
	/** 
	 * @ORM\Id
	 * @ORM\GeneratedValue(strategy="AUTO")
	 * @ORM\Column(type="integer")
	 */
	protected $id;
	
 	/** 
 	 * @ORM\ManyToOne(targetEntity="Antenna", inversedBy="mainfrequencies") 
 	 * @Annotation\Type("Zend\Form\Element\Select")
	 * @Annotation\Required(true)
	 * @Annotation\Options({"label":"Antenne principale :", "empty_option":"Choisir l'antenne principale"})
 	 */
	protected $mainantenna;
	
	/** 
	 * @ORM\ManyToOne(targetEntity="Antenna", inversedBy="backupfrequencies") 
	 * @Annotation\Type("Zend\Form\Element\Select")
	 * @Annotation\Required(true)
	 * @Annotation\Options({"label":"Antenne secours :", "empty_option":"Choisir l'antenne secours"})
	 */
	protected $backupantenna;
	
	/** 
	 * @ORM\OneToOne(targetEntity="Sector", inversedBy="frequency")
	 * @Annotation\Type("Zend\Form\Element\Select")
	 * @Annotation\Required(true)
	 * @Annotation\Options({"label":"Secteur par défaut :", "empty_option":"Choisir le secteur"})
	 */
	protected $defaultsector;
	
	/** 
	 * @ORM\Column(type="decimal", precision=6, scale=3)
	 * @Annotation\Type("Zend\Form\Element\Text")
     * @Annotation\Required({"required":"true"})
     * @Annotation\Options({"label":"Valeur :"})
	 */
	protected $value;
	
	public function getId(){
		return $this->id;
	}
	
	public function getValue(){
		return $this->value;
	}
	
	public function setValue($value){
		$this->value = $value;
	}
	
	public function getDefaultsector(){
		return $this->defaultsector;
	}
	
	public function setDefaultsector($defaultsector){
		$this->defaultsector = $defaultsector;
	}
	
	public function setMainantenna($mainantenna){
		$this->mainantenna = $mainantenna;
	}
	
	public function getMainantenna(){
		return $this->mainantenna;
	}
	
	public function setBackupantenna($backupantenna){
		$this->backupantenna = $backupantenna;
	}
	
	public function getBackupantenna(){
		return $this->backupantenna;
	}
	
	public function getArrayCopy() {
		$object_vars = get_object_vars($this);
		$object_vars['mainantenna'] = ($this->mainantenna ? $this->mainantenna->getId() : null);
		$object_vars['backupantenna'] = ($this->backupantenna ? $this->backupantenna->getId() : null);
		$object_vars['defaultsector'] = ($this->defaultsector ? $this->defaultsector->getId() : null);
		return $object_vars;
	}
}