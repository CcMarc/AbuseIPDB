<?php
/**
 * Module: AbuseIPDBO
 *
 * @author marcopolo & chatgpt
 * @copyright 2023
 * @license MIT License - https://opensource.org/licenses/MIT
 * @version v1.0.0
 * @since 4-14-2023
 */
$autoLoadConfig[0][] = array ('autoType'   => 'class',
                                'loadFile'   => 'observers/class.abuseipdb_observer.php');
$autoLoadConfig[0][] = array ('autoType'   => 'classInstantiate',
                                'className'  => 'abuseipdb_observer',
                                'objectName' => 'abuseipdb_observer');
								