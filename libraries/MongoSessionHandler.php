<?php

/**
 * MongoSessionHandler.php
 *
 * @version 1.0
 * @since   2018
 * @author  Mandar Dhasal (mandar.dhasal@gmail.com) 
 * @date    25 April 2018
 */

/**
 * SessionHandler Class
 * A Handler class for implementation of PHP sessions in mongo db with ttl
 *
 * @subpackage Classes
 * @category   Sessions
 * @author     Mandar Dhasal (mandar.dhasal@gmail.com)
 */


class MongoSessionHandler implements SessionHandlerInterface
{    

    private $docExist = false;


    /**
     * __construct
     * Constructor 
     * verify library
     * initialize mongo connections with DB
     */
    public function __construct(){

        $this->sess_id_prefix = 'mongo_sess_development_'; //set according to environment

        //connect to mongodb
        if ( !extension_loaded("MongoDB") ) 
                trigger_error("Cannot create sessions. MongoDB extension error.", E_USER_ERROR);


        if(!class_exists("MongoDB\Client"))
             trigger_error("Cannot create sessions. MongoDB lib error.", E_USER_ERROR);
            

        //require_once  <your - config - file - with connection config>;
        
        $params = array(

            'host'=>'192.168.2.77', //required
            
            'port'=>'27017', //required

            'database'=>'php_sessions', //required
            
            'collection'=>'php_sessions', // required
        
            'sessionTimeout' => 86400, //TTL  //1 day of inactivity
            
            'connectTimeoutMS' => 3000, //required
            
            'socketTimeoutMS' => 3000, //required
            
            'serverSelectionTimeoutMS'=>3000, //required

            'user'=>'php_sess_user', //optional
            
            'password'=>'php_sess_pass', // optional
             
            'authSource'=>'admin', // optional
            
            //'replicaSet' => 'my-replication-set',  //optional
            
            //'readPreference' => 'primaryPreferred' //optional
        );
       
        $uri = 'mongodb://'.$params['host'].':'.$params['port'].'/'.$params['database'];   

        if( !empty($params['user'])  ){
            
            $uriOptions['username'] = $params['user'];
            $uriOptions['password'] = $params['password'];
            $uriOptions['authSource'] = $params['authSource'];    
        }

        if( !empty($params['replicaSet']) ){

           // $uri = 'mongodb://'.$params['host'].':'.$params['port'].','.$params['host2'].':'.$params['port'].'/'.$params['database'];

            $uriOptions['replicaSet'] = $params['replicaSet'];  
            $uriOptions['readPreference'] = $params['readPreference'];
        }
        
        $uriOptions['connectTimeoutMS'] = $params['connectTimeoutMS'];
        $uriOptions['socketTimeoutMS'] = $params['socketTimeoutMS'];
        $uriOptions['serverSelectionTimeoutMS'] = $params['serverSelectionTimeoutMS'];

        try{

            $this->client = new MongoDB\Client($uri,$uriOptions);   

            $this->client->selectDatabase($params['database']);

            $this->client->{$params['database']}->listCollections();

            $this->coll = $this->client->selectCollection($params['database'], $params['collection']);

            
            // checks collection exist or not. and creates if not exist
            $indexes = $this->coll->listIndexes();

            $indexFound = false;

            foreach ($indexes as $indexInfo) {

                if( isset($indexInfo['expireAfterSeconds']) && isset($indexInfo['key']['expireAt']) ){
                    $indexFound = true;
                
                } 
            }

            if($indexFound == false){
                $this->coll->createIndex( array('expireAt'=> 1), array(  'expireAfterSeconds' => 0    ));  
            }         
           
        }catch (Exception $e){

            // Cannot create sessions. MongoDB
            //print_r($e);
            trigger_error('Connot connect to mongodb.', E_USER_ERROR);
        
        }

        // counting expire time directly by adding seconds, / can also do $date->add(new DateInterval('PT60S'));
        $this->expireAt = new MongoDB\BSON\UTCDateTime( ( (new DateTime())->getTimestamp()+$params['sessionTimeout']) *1000);

    }

    /**
     * open
     * opens a file to write but no needed here 
     * @param savePath <string>
     * @param sessionName <string>  
     * @return Boolean  
     */
    public function open($savePath, $sessionName){
        return true;
    }

    /**
     * open
     * closes a file opened for write but no needed here 
     * @param savePath <string>
     * @param sessionName <string> 
     * @return Boolean
     */
    public function close(){
        return true;
    }

    /**
     * read
     * reads the data from database
     * @param id <string>
     * @return array
     */
    public function read($id){   
        $sess_id = $this->sess_id_prefix.$id ;

        $findResult = $this->coll->findOne(array('sess_id' => $sess_id));        
        
        if($findResult==false)
        {

            $this->docExist = false;
            return '';
        }

        $this->docExist = true;


        // in case of read operation we..  update only after 5 mins . 
        // as we are already updating on write operations
        /*
        $expireAt = $findResult->expireAt->toDateTime()->getTimestamp();
        if( time() - ($expireAt - SESS_TIMEOUT) > 300 ){

            $updateResult = $this->coll->updateOne( 
                                                array('sess_id' => $sess_id), 
                                                
                                                array('$set' => array( 'expireAt'=> $this->expireAt ) ) ) ;

        } 
        */
        return $findResult->data;
    }

    /**
     * write
     * writes the data to database 
     * @param id <string>
     * @param data array
     * @return Boolean
     */
    public function write($id, $data){

        $sess_id = $this->sess_id_prefix.$id;


        // create if row / document not exist
        if($this->docExist === false)
        {
           

            $insertResult = $this->coll->insertOne( array('sess_id' => $sess_id, 'data' => $data, 'expireAt' => $this->expireAt  ) );

            return ( $insertResult->getInsertedCount() == 1 );

        
        }
        else
        {

            $updateResult = $this->coll->updateOne( 
                                                    array('sess_id' => $sess_id), 
                                                    
                                                    array('$set' => array('data' => $data, 'expireAt'=> $this->expireAt) ) 

                                                    );

            return ( $updateResult->getMatchedCount() == 1 );
        
        }
    }

    /**
     * destroy
     * destroy the data from database 
     * @param id <string>
     * @return Boolean
     */
    public function destroy($id){
        $sess_id = $this->sess_id_prefix.$id;
        $deleteResult = $this->coll->deleteOne(array('sess_id'=>$sess_id ));
        return true;
    }

     /**
     * gc
     * garbage collection of the old unused / inactive data.
     * @param id <string>
     * @return Boolean
     */
    public function gc($maxlifetime){
        $time = new MongoDB\BSON\UTCDateTime( ( (new DateTime())->getTimestamp() - $maxlifetime ) *1000);
        $deleteResult = $this->coll->deleteOne( array('expireAt'=> array('$lt'=> $time) ) );
        return true;
    }


}



?>