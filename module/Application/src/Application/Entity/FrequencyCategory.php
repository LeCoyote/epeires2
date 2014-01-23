<?php
/** 
 * Epeires 2
*
* Catégorie d'évènements.
* Peut avoir une catégorie parente.
*
* @copyright Copyright (c) 2013 Bruno Spyckerelle
* @license   https://www.gnu.org/licenses/agpl-3.0.html Affero Gnu Public License
*/
namespace Application\Entity;

use Zend\Form\Annotation;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
/**
 * @ORM\Entity(repositoryClass="Application\Repository\CategoryRepository")
 **/
class FrequencyCategory extends Category{
	
	/**
	 * Ref to the field used to store the state of the frequency
	 * @ORM\OneToOne(targetEntity="CustomField")
	 */
	protected $statefield;
	
	/**
	 * @ORM\OneToOne(targetEntity="CustomField")
	 */
	protected $normalcovfield;
	
	/**
	 * @ORM\OneToOne(targetEntity="CustomField")
	 */
	protected $backupcovfield;
	
	public function getStatefield(){
		return $this->statefield;
	}
	
	public function setStatefield($statefield){
		$this->statefield = $statefield;
	}
	
	public function getNormalAntennafield(){
		return $this->normalcovfield;
	}
	
	public function setNormalAntennafield($normalcovfield){
		$this->normalcovfield = $normalcovfield;
	}
	
	public function getBackupAntennafield(){
		return $this->backupcovfield;
	}
	
	public function setBackupAntennafield($backupcovfield){
		$this->backupcovfield = $normalcovfield;
	}
}