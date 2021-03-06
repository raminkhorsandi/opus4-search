<?php

/**
 * This file is part of OPUS. The software OPUS has been originally developed
 * at the University of Stuttgart with funding from the German Research Net,
 * the Federal Department of Higher Education and Research and the Ministry
 * of Science, Research and the Arts of the State of Baden-Wuerttemberg.
 *
 * OPUS 4 is a complete rewrite of the original OPUS software and was developed
 * by the Stuttgart University Library, the Library Service Center
 * Baden-Wuerttemberg, the Cooperative Library Network Berlin-Brandenburg,
 * the Saarland University and State Library, the Saxon State Library -
 * Dresden State and University Library, the Bielefeld University Library and
 * the University Library of Hamburg University of Technology with funding from
 * the German Research Foundation and the European Regional Development Fund.
 *
 * LICENCE
 * OPUS is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the Licence, or any later version.
 * OPUS is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU General Public License for more
 * details. You should have received a copy of the GNU General Public License
 * along with OPUS; if not, write to the Free Software Foundation, Inc., 51
 * Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 *
 * @category    Tests
 * @author      Ralf Claussnitzer <ralf.claussnitzer@slub-dresden.de>
 * @author      Thoralf Klein <thoralf.klein@zib.de>
 * @copyright   Copyright (c) 2008-2010, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 */
// Setup error reporting.
// TODO leave to Zend and config?
error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', 1);

// Define path to application directory
defined('APPLICATION_PATH')
|| define('APPLICATION_PATH', realpath(dirname(dirname(dirname(__FILE__)))));

// Define application environment (use 'production' by default)
define('APPLICATION_ENV', 'testing');

// Configure include path.
$scriptDir = dirname(__FILE__);

require_once APPLICATION_PATH . DIRECTORY_SEPARATOR . 'vendor/' . 'autoload.php';

// Do test environment initializiation.
$application = new Zend_Application(
    APPLICATION_ENV,
    [
        "config" => [
            APPLICATION_PATH . DIRECTORY_SEPARATOR . 'test' . DIRECTORY_SEPARATOR . 'config.ini'
        ]
    ]
);

// TODO should not be necessary for search tests
Zend_Registry::set('opus.disableDatabaseVersionCheck', true);

$application->bootstrap(['Database','Temp','OpusLocale','IndexPlugin']);
