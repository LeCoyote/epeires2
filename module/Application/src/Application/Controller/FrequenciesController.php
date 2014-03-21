<?php

/**
 * Epeires 2
 *
 * @license   https://www.gnu.org/licenses/agpl-3.0.html Affero Gnu Public License
 */

namespace Application\Controller;

use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Zend\Session\Container;
use Application\Entity\Event;
use Application\Entity\CustomFieldValue;
use Application\Entity\Frequency;
use Application\Entity\FrequencyCategory;
use Doctrine\Common\Collections\Criteria;

class FrequenciesController extends ZoneController {

    public function indexAction() {

        parent::indexAction();


        $viewmodel = new ViewModel();

        $return = array();

        if ($this->flashMessenger()->hasErrorMessages()) {
            $return['errorMessages'] = $this->flashMessenger()->getErrorMessages();
        }

        if ($this->flashMessenger()->hasSuccessMessages()) {
            $return['successMessages'] = $this->flashMessenger()->getSuccessMessages();
        }

        $this->flashMessenger()->clearMessages();

        $em = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager');

        $qb = $em->createQueryBuilder();
        $qb->select(array('s', 'z'))
                ->from('Application\Entity\SectorGroup', 's')
                ->leftJoin('s.zone', 'z')
                ->andWhere($qb->expr()->eq('s.display', true))
                ->orderBy('s.position', 'ASC');

        $session = new Container('zone');
        $zonesession = $session->zoneshortname;

        if ($zonesession != null) {
            if ($zonesession != '0') {
                $orga = $em->getRepository('Application\Entity\Organisation')->findOneBy(array('shortname' => $zonesession));
                if ($orga) {
                    $qb->andWhere($qb->expr()->eq('z.organisation', $orga->getId()));
                } else {
                    $zone = $em->getRepository('Application\Entity\QualificationZone')->findOneBy(array('shortname' => $zonesession));
                    if ($zone) {
                        $qb->andWhere($qb->expr()->andX(
                                        $qb->expr()->eq('z.organisation', $zone->getOrganisation()->getId()), $qb->expr()->eq('s.zone', $zone->getId())
                        ));
                    } else {
                        //error
                    }
                }
            }
        }
        if (($zonesession == null || ($zonesession != null && $zonesession == '0')) && $this->zfcUserAuthentication()->hasIdentity()) {
            $orga = $this->zfcUserAuthentication()->getIdentity()->getOrganisation();
            $qb->andWhere($qb->expr()->eq('z.organisation', $orga->getId()));
        }

        //pas de session, pas d'utilisateur connecté => tout ?

        $groups = $qb->getQuery()->getResult();

        $criteria = Criteria::create();
        $criteria->andWhere(Criteria::expr()->isNull('defaultsector'));
        $otherfrequencies = $em->getRepository('Application\Entity\Frequency')->matching($criteria);

        $viewmodel->setVariables(array('antennas' => $this->getAntennas(),
            'messages' => $return,
            'groups' => $groups,
            'other' => $otherfrequencies));

        return $viewmodel;
    }

    public function switchfrequencyAction() {
        $json = array();
        $messages = array();

        if ($this->isGranted('events.write') && $this->zfcUserAuthentication()->hasIdentity()) {
            $fromid = $this->params()->fromQuery('fromid', null);
            $toid = $this->params()->fromQuery('toid', null);

            $now = new \DateTime('NOW');
            $now->setTimezone(new \DateTimeZone("UTC"));

            if ($fromid && $toid) {
                $em = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager');

                $fromfreq = $em->getRepository('Application\Entity\Frequency')->find($fromid);
                $tofreq = $em->getRepository('Application\Entity\Frequency')->find($toid);

                if ($fromfreq && $tofreq) {

                    //recherche des évènements sur la fréquence d'origine
                    $events = $em->getRepository('Application\Entity\Frequency')->getCurrentEvents('Application\Entity\FrequencyCategory');
                    $frequencyEvents = array();
                    foreach ($events as $event) {
                        $frequencyfield = $event->getCategory()->getFrequencyField();
                        foreach ($event->getCustomFieldsValues() as $value) {
                            if ($value->getCustomField()->getId() == $frequencyfield->getId()) {
                                if ($value->getValue() == $fromid) {
                                    $frequencyEvents[] = $event;
                                }
                            }
                        }
                    }
                    //0 evt : on en crée un nouveau
                    //1 evt : on modifie
                    //2 ou + : indécidable -> erreur
                    if (count($frequencyEvents) == 0) {
                        //on crée l'evt "passage en couv secours"
                        $event = new Event();
                        $status = $em->getRepository('Application\Entity\Status')->find('2');
                        $impact = $em->getRepository('Application\Entity\Impact')->find('3');
                        $event->setImpact($impact);
                        $event->setStatus($status);
                        $event->setStartdate($now);
                        $event->setPunctual(false);
                        //TODO fix horrible en attendant de gérer correctement les fréquences sans secteur
                        if ($fromfreq->getDefaultsector()) {
                            $event->setOrganisation($fromfreq->getDefaultsector()->getZone()->getOrganisation());
                            $event->addZonefilter($fromfreq->getDefaultsector()->getZone());
                        } else {
                            $event->setOrganisation($this->zfcUserAuthentication()->getIdentity()->getOrganisation());
                        }
                        $event->setAuthor($this->zfcUserAuthentication()->getIdentity());
                        $categories = $em->getRepository('Application\Entity\FrequencyCategory')->findAll();
                        //TODO paramétrer la catégorie au lieu de prendre la première
                        if ($categories) {
                            $cat = $categories[0];
                            $event->setCategory($cat);
                            $frequencyfieldvalue = new CustomFieldValue();
                            $frequencyfieldvalue->setCustomField($cat->getFrequencyfield());
                            $frequencyfieldvalue->setEvent($event);
                            $frequencyfieldvalue->setValue($fromfreq->getId());
                            $event->addCustomFieldValue($frequencyfieldvalue);
                            $statusfield = new CustomFieldValue();
                            $statusfield->setCustomField($cat->getStatefield());
                            $statusfield->setEvent($event);
                            $statusfield->setValue(false); //available
                            $event->addCustomFieldValue($statusfield);
                            $freqfield = new CustomFieldValue();
                            $freqfield->setCustomField($cat->getOtherFrequencyField());
                            $freqfield->setEvent($event);
                            $freqfield->setValue($tofreq->getId());
                            $event->addCustomFieldValue($freqfield);
                            $em->persist($frequencyfieldvalue);
                            $em->persist($statusfield);
                            $em->persist($freqfield);
                            $em->persist($event);
                            try {
                                $em->flush();
                                $messages['success'][] = "Changement de fréquence pour " . $fromfreq->getName() . " enregistré.";
                            } catch (\Exception $e) {
                                $messages['error'][] = $e->getMessage();
                            }
                        } else {
                            $messages['error'][] = "Erreur : aucune catégorie trouvée.";
                        }
                    } else if (count($frequencyEvents) == 1) {
                        $event = $frequencyEvents[0];
                        //deux cas : changement de fréquence ou retour à la fréquence nominale
                        //dans le deuxième cas, il faut fermer l'évènement si couv normale et freq dispo
                        if($fromid == $toid) {
                            //on vérifie que l'évènement existant a bien un champ changement de fréquence
                            $previousfield = null;
                            $otherfields = false;
                            foreach ($event->getCustomFieldsValues() as $value){
                                if($value->getCustomField()->getId() == $event->getCategory()->getOtherFrequencyField()->getId()){
                                    $previousfield = $value;
                                } else if ($value->getCustomField()->getId() == $event->getCategory()->getStatefield()->getId()) {
                                    if($value->getValue() == true){
                                        $otherfields = true;
                                    }
                                } else if($value->getCustomField()->getId() == $event->getCategory()->getCurrentAntennafield()->getId()){
                                    if($value->getValue() == 1){
                                        $otherfields = true;
                                    }
                                }
                            }
                            if($previousfield){
                                //si il y a d'autres champs, on ne ferme pas l'évènement
                                //sinon on ferme
                                if($otherfields) {
                                    $previousfield->setValue($toid);
                                    $em->persist($previousfield);
                                } else {
                                    $endstatus = $em->getRepository('Application\Entity\Status')->find('3');
                                    $event->setEnddate($now);
                                    $event->setStatus($endstatus);
                                }
                                $em->persist($event);
                                try {
                                    $em->flush();
                                    $messages['success'][] = "Fréquence mise à jour";
                                } catch (\Exception $ex) {
                                    $messages['error'][] = $ex->getMessage();
                                }
                            } else {
                                $messages['error'][] = "Erreur : fréquence identique.";
                            }
                        } else {
                            $previousfield = null;
                            foreach ($event->getCustomFieldsValues() as $value){
                                if($value->getCustomField()->getId() == $event->getCategory()->getOtherFrequencyField()->getId()){
                                    $previousfield = $value;
                                }
                            }
                            if($previousfield){
                                $previousfield->setValue($toid);
                                $em->persist($previousfield);
                            } else {
                                $customvalue = new CustomFieldValue();
                                $customvalue->setEvent($event);
                                $customvalue->setCustomField($event->getCategory()->getOtherFreqField());
                                $customvalue->setValue($toid);
                                $event->addCustomFieldValue($customfieldvalue);
                                $em->persist($customvalue);
                                $em->persist($event);
                            }
                            try {
                                $em->flush();
                                $messages['success'][] = "Evénement modifié";
                            } catch (\Exception $ex) {
                                $messages['error'][] = $e->getMessage();
                            }
                        }
                    } else {
                        $messages['error'][] = "Impossible de changer de fréquence : plusieurs évènements sur cette fréquence existent déjà.";
                    }
                } else {
                    $messages['error'][] = "Impossible de trouver les fréquences à échanger.";
                }
            }
        } else {
            $messages['error'][] = "Droits insuffisants";
        }
        $json['messages'] = $messages;
        return new JsonModel($json);
    }

    public function switchantennaAction() {
        $json = array();
        $messages = array();
        if ($this->isGranted('events.write') && $this->zfcUserAuthentication()->hasIdentity()) {
            $em = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager');
            $state = $this->params()->fromQuery('state', null);
            $antennaid = $this->params()->fromQuery('antennaid', null);

            $now = new \DateTime('NOW');
            $now->setTimezone(new \DateTimeZone("UTC"));

            if ($state != null && $antennaid) {
                $events = $em->getRepository('Application\Entity\Antenna')->getCurrentEvents('Application\Entity\AntennaCategory');
                //on récupère les évènements de l'antenne
                $antennaEvents = array();
                foreach ($events as $event) {
                    $antennafield = $event->getCategory()->getAntennafield();
                    foreach ($event->getCustomFieldsValues() as $value) {
                        if ($value->getCustomField()->getId() == $antennafield->getId()) {
                            if ($value->getValue() == $antennaid) {
                                $antennaEvents[] = $event;
                            }
                        }
                    }
                }

                if ($state == 'true') {
                    //recherche de l'evt à fermer
                    if (count($antennaEvents) == 1) {
                        $event = $antennaEvents[0];
                        $endstatus = $em->getRepository('Application\Entity\Status')->find('3');
                        $event->setStatus($endstatus);
                        //ferme evts fils de type frequencycategory
                        foreach ($event->getChildren() as $child) {
                            if ($child->getCategory() instanceof FrequencyCategory) {
                                $child->setEnddate($now);
                                $child->setStatus($endstatus);
                                $em->persist($child);
                            }
                        }
                        $event->setEnddate($now);
                        $em->persist($event);
                        try {
                            $em->flush();
                            $messages['success'][] = "Evènement antenne correctement terminé.";
                        } catch (\Exception $e) {
                            $messages['error'][] = $e->getMessage();
                        }
                    } else {
                        $messages['error'][] = "Impossible de déterminer l'évènement à terminer";
                    }
                } else {
                    if (count($antennaEvents) > 0) {
                        $messages['error'][] = "Un évènement est déjà en cours, impossible d'en créer un nouveau.";
                    } else {
                        $event = new Event();
                        $status = $em->getRepository('Application\Entity\Status')->find('2');
                        $impact = $em->getRepository('Application\Entity\Impact')->find('3');
                        $event->setStatus($status);
                        $event->setStartdate($now);
                        $event->setImpact($impact);
                        $event->setPunctual(false);
                        $antenna = $em->getRepository('Application\Entity\Antenna')->find($antennaid);
                        $event->setOrganisation($antenna->getOrganisation()); //TODO et si une antenne appartient à plusieurs orga ?
                        $event->setAuthor($this->zfcUserAuthentication()->getIdentity());
                        $categories = $em->getRepository('Application\Entity\AntennaCategory')->findAll();
                        if ($categories) {
                            $cat = $categories[0];
                            $antennafieldvalue = new CustomFieldValue();
                            $antennafieldvalue->setCustomField($cat->getAntennaField());
                            $antennafieldvalue->setValue($antennaid);
                            $antennafieldvalue->setEvent($event);
                            $event->addCustomFieldValue($antennafieldvalue);
                            $statusvalue = new CustomFieldValue();
                            $statusvalue->setCustomField($cat->getStatefield());
                            $statusvalue->setValue(true);
                            $statusvalue->setEvent($event);
                            $event->addCustomFieldValue($statusvalue);
                            $event->setCategory($categories[0]);
                            $em->persist($antennafieldvalue);
                            $em->persist($statusvalue);
                            $em->persist($event);
                            //création des evts fils pour le passage en secours
                            foreach ($antenna->getMainfrequencies() as $frequency) {
                                $this->switchCoverture($messages, $frequency, 1, $now, $event);
                            }
                            foreach ($antenna->getMainfrequenciesclimax() as $frequency) {
                                $this->switchCoverture($messages, $frequency, 1, $now, $event);
                            }
                            try {
                                $em->flush();
                                $messages['success'][] = "Nouvel évènement antenne créé.";
                            } catch (\Exception $e) {
                                $messages['error'][] = $e->getMessage();
                            }
                        } else {
                            $messages['error'][] = "Impossible de créer un nouvel évènement. Contactez l'administrateur.";
                        }
                    }
                }
            } else {
                $messages['error'][] = "Requête incorrecte, impossible de trouver l'antenne correspondante.";
            }
        } else {
            $messages['error'][] = 'Droits insuffisants pour modifier l\'état de l\'antenne.';
        }
        $json['messages'] = $messages;
        $json['frequencies'] = $this->getFrequencies(true);
        return new JsonModel($json);
    }

    public function switchCovertureAction() {
        $messages = array();
        if ($this->isGranted('events.write') && $this->zfcUserAuthentication()->hasIdentity()) {
            $em = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager');
            $cov = $this->params()->fromQuery('cov', null);
            $frequencyid = $this->params()->fromQuery('frequencyid', null);

            if ($cov != null && $frequencyid) {
                $now = new \DateTime('NOW');
                $now->setTimezone(new \DateTimeZone("UTC"));
                $freq = $em->getRepository('Application\Entity\Frequency')->find($frequencyid);
                if ($freq) {
                    $this->switchCoverture($messages, $freq, intval($cov), $now);
                } else {
                    $messages['error'][] = "Impossible de trouver la fréquence demandée";
                }
            } else {
                $messages['error'][] = "Paramètres incorrects, impossible de créer l'évènement.";
            }
        } else {
            $messages['error'][] = "Droits insuffisants";
        }
        return new JsonModel($messages);
    }

    /**
     * Create and persist a new frequency event
     * @param unknown $cov 0=principale, 1 = secours
     * @param Event $parent
     */
    private function switchCoverture(&$messages, Frequency $frequency, $cov, $startdate, Event $parent = null) {
        $em = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager');
        if ($cov == 0) {
            $frequencyevents = array();
            //on cloture l'evt "passage en couv secours"
            foreach ($em->getRepository('Application\Entity\Frequency')->getCurrentEvents('Application\Entity\FrequencyCategory') as $event) {
                $frequencyfield = $event->getCategory()->getFrequencyfield()->getId();
                foreach ($event->getCustomFieldsValues() as $customvalue) {
                    if ($customvalue->getCustomField()->getId() == $frequencyfield) {
                        if ($customvalue->getValue() == $frequency->getId()) {
                            $frequencyevents[] = $event;
                        }
                    }
                }
            }
            if (count($frequencyevents) == 0 || count($frequencyevents) > 1) {
                $messages['error'][] = "Impossible de trouver l'évènement à cloturer";
            } else {
                $event = $frequencyevents[0];
                $endstatus = $em->getRepository('Application\Entity\Status')->find('3');
                $event->setStatus($endstatus);
                $event->setEnddate($startdate);
                $em->persist($event);
                try {
                    $em->flush();
                } catch (\Exception $e) {
                    $messages['error'][] = $e->getMessage();
                }
            }
        } else {
            //on crée l'evt "passage en couv secours"
            $event = new Event();
            if ($parent) {
                $event->setParent($parent);
            }
            $status = $em->getRepository('Application\Entity\Status')->find('2');
            $impact = $em->getRepository('Application\Entity\Impact')->find('3');
            $event->setImpact($impact);
            $event->setStatus($status);
            $event->setStartdate($startdate);
            $event->setPunctual(false);
            //TODO fix horrible en attendant de gérer correctement les fréquences sans secteur
            if ($frequency->getDefaultsector()) {
                $event->setOrganisation($frequency->getDefaultsector()->getZone()->getOrganisation());
                $event->addZonefilter($frequency->getDefaultsector()->getZone());
            } else {
                $event->setOrganisation($this->zfcUserAuthentication()->getIdentity()->getOrganisation());
            }
            $event->setAuthor($this->zfcUserAuthentication()->getIdentity());
            $categories = $em->getRepository('Application\Entity\FrequencyCategory')->findAll();
            //TODO paramétrer la catégorie au lieu de prendre la première
            if ($categories) {
                $cat = $categories[0];
                $event->setCategory($cat);
                $frequencyfieldvalue = new CustomFieldValue();
                $frequencyfieldvalue->setCustomField($cat->getFrequencyfield());
                $frequencyfieldvalue->setEvent($event);
                $frequencyfieldvalue->setValue($frequency->getId());
                $event->addCustomFieldValue($frequencyfieldvalue);
                $statusfield = new CustomFieldValue();
                $statusfield->setCustomField($cat->getStatefield());
                $statusfield->setEvent($event);
                $statusfield->setValue(true); //unavailable
                $event->addCustomFieldValue($statusfield);
                $covfield = new CustomFieldValue();
                $covfield->setCustomField($cat->getCurrentAntennafield());
                $covfield->setEvent($event);
                $covfield->setValue($cov);
                $event->addCustomFieldValue($covfield);
                $em->persist($frequencyfieldvalue);
                $em->persist($statusfield);
                $em->persist($covfield);
                $em->persist($event);
                try {
                    $em->flush();
                    $messages['success'][] = "Changement de couverture de la fréquence " . $frequency->getValue() . " enregistré.";
                } catch (\Exception $e) {
                    $messages['error'][] = $e->getMessage();
                }
            } else {
                $messages['error'][] = "Impossible de passer les couvertures en secours : aucune catégorie trouvée.";
            }
        }
    }

    public function getAntennaStateAction() {
        return new JsonModel($this->getAntennas(true));
    }

    /**
     * State of the frequencies
     * @return \Zend\View\Model\JsonModel
     */
    public function getFrequenciesStateAction() {
        return new JsonModel($this->getFrequencies(true));
    }

    private function getFrequencies($full = true) {
        $em = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager');

        $frequencies = array();
        $results = $em->getRepository('Application\Entity\Frequency')->findAll();

        //retrieve antennas state once and for all
        $antennas = $this->getAntennas(false);

        foreach ($results as $frequency) {
            if ($full) {
                $frequencies[$frequency->getId()] = array();
                $frequencies[$frequency->getId()]['name'] = $frequency->getValue();
                $frequencies[$frequency->getId()]['status'] = true;
                $frequencies[$frequency->getId()]['cov'] = 0;
                $frequencies[$frequency->getId()]['otherfreq'] = 0;
                $frequencies[$frequency->getId()]['otherfreqid'] = 0;
                $frequencies[$frequency->getId()]['otherfreqname'] = '';
                $frequencies[$frequency->getId()]['planned'] = false;
                $frequencies[$frequency->getId()]['main'] = $frequency->getMainantenna()->getId();
                $frequencies[$frequency->getId()]['backup'] = $frequency->getBackupAntenna()->getId();
                if ($frequency->getMainantennaclimax()) {
                    $frequencies[$frequency->getId()]['mainclimax'] = $frequency->getMainantennaclimax()->getId();
                }
                if ($frequency->getBackupantennaclimax()) {
                    $frequencies[$frequency->getId()]['backupclimax'] = $frequency->getBackupantennaclimax()->getId();
                }
            } else {
                $frequencies[$frequency->getId()] = true;
            }

            if ($full) {
                $frequencies[$frequency->getId()]['status'] *= $antennas[$frequency->getMainAntenna()->getId()] * $antennas[$frequency->getBackupAntenna()->getId()];
            } else {
                $frequencies[$frequency->getId()] *= $antennas[$frequency->getMainAntenna()->getId()] * $antennas[$frequency->getBackupAntenna()->getId()];
            }
        }

        foreach ($em->getRepository('Application\Entity\Frequency')->getCurrentEvents('Application\Entity\FrequencyCategory') as $event) {
            $statefield = $event->getCategory()->getStatefield()->getId();
            $frequencyfield = $event->getCategory()->getFrequencyfield()->getId();
            $covfield = $event->getCategory()->getCurrentAntennafield()->getId();
            $otherfreqfield = $event->getCategory()->getOtherFrequencyfield()->getId();
            $frequencyid = 0;
            $otherfreqid = 0;
            $available = true;
            $cov = 0;
            foreach ($event->getCustomFieldsValues() as $customvalue) {
                if ($customvalue->getCustomField()->getId() == $statefield) {
                    $available = !$customvalue->getValue();
                } else if ($customvalue->getCustomField()->getId() == $frequencyfield) {
                    $frequencyid = $customvalue->getValue();
                } else if ($customvalue->getCustomField()->getId() == $covfield) {
                    $cov = $customvalue->getValue();
                } else if ($customvalue->getCustomField()->getId() == $otherfreqfield) {
                    $otherfreqid = $customvalue->getValue();
                }
            }
            if (array_key_exists($frequencyid, $frequencies)) { //peut être inexistant si la fréquence a été supprimée alors que des évènements existent
                if ($full) {
                    $frequencies[$frequencyid]['status'] *= $available;
                    $frequencies[$frequencyid]['cov'] = $cov;
                    $otherfreq = $em->getRepository('Application\Entity\Frequency')->find($otherfreqid);
                    if ($otherfreq) {
                        $frequencies[$frequencyid]['otherfreq'] = $otherfreq->getValue();
                        $frequencies[$frequencyid]['otherfreqname'] = $otherfreq->getName();
                        $frequencies[$frequencyid]['otherfreqid'] = $otherfreq->getId();
                        $frequencies[$frequencyid]['main'] = $otherfreq->getMainantenna()->getId();
                        $frequencies[$frequencyid]['backup'] = $otherfreq->getBackupantenna()->getId();
                        if ($otherfreq->getMainantennaclimax()) {
                            $frequencies[$frequencyid]['mainclimax'] = $otherfreq->getMainantennaclimax()->getId();
                        }
                        if ($otherfreq->getBackupantennaclimax()) {
                            $frequencies[$frequencyid]['backupclimax'] = $otherfreq->getBackupantennaclimax()->getId();
                        }
                    } else {
                        
                    }
                } else {
                    $frequencies[$frequencyid] *= $available;
                }
            }
        }

        if ($full) { //en format complet, on donne aussi les evènements dans les 12h
            foreach ($em->getRepository('Application\Entity\Frequency')->getPlannedEvents('Application\Entity\FrequencyCategory') as $event) {
                $statefield = $event->getCategory()->getStatefield()->getId();
                $frequencyfield = $event->getCategory()->getFrequencyfield()->getId();
                $frequencyid = 0;
                $planned = false;
                foreach ($event->getCustomFieldsValues() as $customvalue) {
                    if ($customvalue->getCustomField()->getId() == $statefield) {
                        $planned = $customvalue->getValue();
                    } else if ($customvalue->getCustomField()->getId() == $frequencyfield) {
                        $frequencyid = $customvalue->getValue();
                    }
                }
                if (array_key_exists($frequencyid, $frequencies)) { //peut être inexistant si la fréquence a été supprimée alors que des évènements existent
                    $frequencies[$frequencyid]['planned'] = $planned;
                }
            }
        }

        return $frequencies;
    }

    private function getAntennas($full = true) {
        $em = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager');

        $antennas = array();

        foreach ($em->getRepository('Application\Entity\Antenna')->findAll() as $antenna) {
            //avalaible by default
            if ($full) {
                $antennas[$antenna->getId()] = array();
                $antennas[$antenna->getId()]['name'] = $antenna->getName();
                $antennas[$antenna->getId()]['shortname'] = $antenna->getShortname();
                $antennas[$antenna->getId()]['status'] = true;
                $antennas[$antenna->getId()]['planned'] = false;
            } else {
                $antennas[$antenna->getId()] = true;
            }
        }

        foreach ($em->getRepository('Application\Entity\Antenna')->getCurrentEvents('Application\Entity\AntennaCategory') as $result) {
            $statefield = $result->getCategory()->getStatefield()->getId();
            $antennafield = $result->getCategory()->getAntennafield()->getId();
            $antennaid = 0;
            $available = true;
            foreach ($result->getCustomFieldsValues() as $customvalue) {
                if ($customvalue->getCustomField()->getId() == $statefield) {
                    $available = !$customvalue->getValue();
                } else if ($customvalue->getCustomField()->getId() == $antennafield) {
                    $antennaid = $customvalue->getValue();
                }
            }
            if ($full) {
                $antennas[$antennaid]['status'] *= $available;
            } else {
                $antennas[$antennaid] *= $available;
            }
        }

        if ($full) {
            foreach ($em->getRepository('Application\Entity\Antenna')->getPlannedEvents('Application\Entity\AntennaCategory') as $result) {
                $statefield = $result->getCategory()->getStatefield()->getId();
                $antennafield = $result->getCategory()->getAntennafield()->getId();
                $antennaid = 0;
                $planned = false;
                foreach ($result->getCustomFieldsValues() as $customvalue) {
                    if ($customvalue->getCustomField()->getId() == $statefield) {
                        $planned = $customvalue->getValue();
                    } else if ($customvalue->getCustomField()->getId() == $antennafield) {
                        $antennaid = $customvalue->getValue();
                    }
                }
                $antennas[$antennaid]['planned'] = $planned;
            }
        }

        return $antennas;
    }

    public function getfrequenciesAction() {
        $em = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager');
        $frequencyid = $this->params()->fromQuery('id', null);
        $frequencies = array();
        if ($frequencyid) {
            $frequency = $em->getRepository('Application\Entity\Frequency')->find($frequencyid);
            $frequencieslist = $em->getRepository('Application\Entity\Frequency')->findBy(array('organisation' => $frequency->getOrganisation()->getId()));
            foreach ($frequencieslist as $freq) {
                $frequencies[$freq->getId()] = ($freq->getDefaultSector() ? $freq->getDefaultSector()->getName() . " " . $freq->getValue() : $freq->getOtherName() . " " . $freq->getValue());
            }
        }
        return new JsonModel($frequencies);
    }

}
