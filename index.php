<?php 
/**
 * MongoSessionHandler Example
 *
 * @version 1.0
 * @since   2018
 * @author  Mandar Dhasal (mandar.dhasal@gmail.com) 
 * @date    25 April 2018
 */

/**
 * index.php 
 * An example for implementation of PHP sessions in mongo db
 * @author     Mandar Dhasal (mandar.dhasal@gmail.com)
 */


//error handling
error_reporting(E_ALL);
ini_set('display_errors', '1');

//include 
require __DIR__.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';
require __DIR__.DIRECTORY_SEPARATOR.'libraries'.DIRECTORY_SEPARATOR.'MongoSessionHandler.php';


// set session handler here
$sesshandler = new MongoSessionHandler();
session_set_save_handler($sesshandler, true);

//session start
session_start();

//set session
$_SESSION['foo'] = 'bar';

//test
echo 'PHP - MongoDB Sessions:- '.$_SESSION['foo'];

?>
