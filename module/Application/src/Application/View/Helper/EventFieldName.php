<?php
/*
 * This file is part of Epeires².
 * Epeires² is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * Epeires² is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Epeires². If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace Application\View\Helper;

use Zend\Form\View\Helper\AbstractHelper;

/**
 *
 * @author Bruno Spyckerelle
 *        
 */
class EventFieldName extends AbstractHelper
{

    public function __invoke($eventfield)
    {
        switch ($eventfield) {
            case 'end_date':
                return 'Date de fin';
            case 'start_date':
                return 'Date de début';
            case 'punctual':
                return 'Ponctuel';
            case 'impact':
                return 'Impact';
            case 'status':
                return 'Statut';
            default:
                return $eventfield;
                break;
        }
    }
}