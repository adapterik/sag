<?php
/*
  Copyright 2010 Sam Bisbee 

   Licensed under the Apache License, Version 2.0 (the "License");
   you may not use this file except in compliance with the License.
   You may obtain a copy of the License at

       http://www.apache.org/licenses/LICENSE-2.0

   Unless required by applicable law or agreed to in writing, software
   distributed under the License is distributed on an "AS IS" BASIS,
   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
   See the License for the specific language governing permissions and
   limitations under the License.
*/

require_once('SagException.php');
require_once('SagCouchException.php');

class Sag
{
  public static $AUTH_BASIC = "AUTH_BASIC";

  private $db;
  private $host;
  private $port;

  private $user;
  private $pass;
  private $authType;

  private $decodeResp = true;

  public function Sag($host = "127.0.0.1", $port = "5984")
  {
    $this->host = $host;
    $this->port = $port;
  }

  public function login($user, $pass, $type = null)
  {
    if(!isset($type))
      $type = Sag::$AUTH_BASIC;

    if($type != Sag::$AUTH_BASIC)
      throw new SagException("Unknown auth type for login()");

    $this->user = $user;
    $this->pass = $pass;
    $this->authType = $type;
  }

  public function decode($decode)
  {
    if(!is_bool($decode))
      throw new SagException('decode() expected a boolean');

    $this->decodeResp = $decode;
  }

  public function get($url)
  {
    if(!$this->db)
      throw new SagException('No database specified');

    return $this->procPacket('GET', "/{$this->db}$url");
  }

  public function delete($id, $rev)
  {
    if(!$this->db)
      throw new SagException('No database specified');

    if(!is_string($id) || !is_string($rev) || empty($id) || empty($rev))
      throw new SagException('delete() expects two strings.');

    return $this->procPacket('DELETE', "/{$this->db}/$id?rev=$rev");
  }

  public function put($id, $data)
  {
    if(!$this->db)
      throw new SagException('No database specified');

    if(!is_string($id))
      throw new SagException('put() expected a string for the doc id.');

    if(!isset($data) || !is_object($data))
      throw new SagException('put() needs an object for data - are you trying to use delete()?');

    return $this->procPacket('PUT', "/{$this->db}/$id", json_encode($data)); 
  }

  public function post($data)
  {
    if(!$this->db)
      throw new SagException('No database specified');

    if(!isset($data) || !is_object($data))
      throw new SagException('post() needs an object for data.');

    return $this->procPacket('POST', "/{$this->db}", json_encode($data)); 
  }

  public function bulk($docs, $allOrNothing = true)
  {
    if(!$this->db)
      throw new SagException('No database specified');

    if(!is_array($docs))
      throw new SagException('bulk() expects an array for its first argument');

    if(!is_bool($allOrNothing))
      throw new SagException('bulk() expects a boolean for its second argument');

    $data = new StdClass();
    //Only send all_or_nothing if it's non-default (true), saving bandwidth.
    if($allOrNothing)
      $data->all_or_nothing = $allOrNothing;

    $data->docs = $docs;

    return $this->procPacket("POST", "/{$this->db}/_bulk_docs", json_encode($data));
  }

  public function copy($srcID, $dstID, $dstRev = null)
  {
    if(!$this->db)
      throw new SagException('No database specified');

    if(empty($srcID) || !is_string($srcID))
      throw new SagException('copy() got an invalid source ID');

    if(empty($dstID) || !is_string($dstID))
      throw new SagException('copy() got an invalid destination ID');

    if($dstRev != null && (empty($dstRev) || !is_string($dstRev)))
      throw new SagException('copy() got an invalid source revision');

    $headers = array(
      "Destination" => "$dstID".(($dstRev) ? "?rev=$dstRev" : "")
    );

    return $this->procPacket('COPY', "/{$this->db}/$srcID", null, $headers); 
  }

  public function setDatabase($db)
  {
    if(!is_string($db))
      throw new SagException('setDatabase() expected a string.');

    $this->db = $db;
  }

  public function getAllDocs($incDocs = false, $limit = null, $startKey = null, $endKey = null)
  {
    if(!$this->db)
      throw new SagException('No database specified.');

    $qry = array();

    if(isset($incDocs))
    {
      if(!is_bool($incDocs))
        throw new SagException('getAllDocs() expected a boolean for include_docs.');

      array_push($qry, "include_docs=true");
    }       

    if(isset($startKey))
    {
      if(!is_string($startKey))
        throw new SagException('getAllDocs() expected a string for startkey.');

      array_push($qry, "startkey=$startKey");
    }

    if(isset($endKey))
    {
      if(!is_string($endKey))
        throw new SagException('getAllDocs() expected a string for endkey.');

      array_push($qry, "endkey=$endKey");
    }

    if(isset($limit))
    {
      if(!is_int($limit) || $limit < 0)
        throw new SagException('getAllDocs() expected a positive integeter for limit.');

      array_push($qry, "limit=$limit");
    }

    return $this->procPacket('GET', "/{$this->db}/_all_docs?".implode('&', $qry));
  }

  public function getAllDatabases()
  {
    return $this->procPacket('GET', '/_all_dbs');
  }

  public function getAllDocsBySeq($incDocs = false, $limit = null, $startKey = null, $endKey = null)
  {
    if(!$this->db)
      throw new SagException('No database specified.');

    $qry = array();

    if(isset($incDocs))
    {
      if(!is_bool($incDocs))
        throw new SagException('getAllDocs() expected a boolean for include_docs.');

      array_push($qry, "include_docs=true");
    }       

    if(isset($startKey))
    {
      if(!is_string($startKey))
        throw new SagException('getAllDocs() expected a string for startkey.');

      array_push($qry, "startkey=$startKey");
    }

    if(isset($endKey))
    {
      if(!is_string($endKey))
        throw new SagException('getAllDocs() expected a string for endkey.');

      array_push($qry, "endkey=$endKey");
    }

    if(isset($limit))
    {
      if(!is_int($limit) || $limit < 0)
        throw new SagException('getAllDocs() expected a positive integeter for limit.');

      array_push($qry, "limit=$limit");
    }

    return $this->procPacket('GET', "/{$this->db}/_all_docs_by_seq?".implode('&', $qry));
  }

  public function generateIDs($num = 10)
  {
    if(!is_int($num) || $num < 0)
      throw new SagException('generateIDs() expected an integer >= 0.');

    return $this->procPacket('GET', "/_uuids?count=$num");
  }

  public function createDatabase($name)
  {
    if(empty($name) || !is_string($name))
      throw new SagException('createDatabase() expected a valid database name');

    return $this->procPacket('PUT', "/$name"); 
  }

  public function deleteDatabase($name)
  {
    if(empty($name) || !is_string($name))
      throw new SagException('deleteDatabase() expected a valid database name');

    return $this->procPacket('DELETE', "/$name");
  }

  public function replicate($src, $target, $continuous = false)
  {
    if(empty($src) || !is_string($src))
      throw new SagException('replicate() is missing a source to replicate from.');

    if(empty($target) || !is_string($target))
      throw new SagException('replicate() is missing a target to replicate to.');

    if(!is_bool($continuous))
      throw new SagException('replicate() expected a boolean for its third argument.');

    $data = new StdClass();
    $data->source = $src;
    $data->target = $target;

    if($continuous)
      $data->continuous = true; //only include if true, decreasing packet size

    return $this->procPacket('POST', '/_replicate', json_encode($data));
  }

  public function compact($viewName = null)
  {
    return $this->procPacket('POST', "/{$this->db}/_compact".((empty($viewName)) ? '' : "/$viewName"));
  }

  private function procPacket($method, $url, $data = null, $headers = array())
  {
    // Do some string replacing for HTTP sanity.
    $url = str_replace(array(" ", "\""), array('%20', '%22'), $url);

    // Open the socket.
    $sock = @fsockopen($this->host, $this->port, $sockErrNo, $sockErrStr);
    if(!$sock)
      throw new SagException(
        "Error connecting to {$this->host}:{$this->port} - $sockErrStr ($sockErrNo)."
      );

    // Build the request packet.
    $headers["Host"] = "{$this->host}:{$this->port}";
    $headers["User-Agent"] = "Sag/.1";
    
    //usernames and passwords can be blank
    if(isset($this->user) || isset($this->pass))
    {
      switch($this->authType)
      {
        case Sag::$AUTH_BASIC:
          $headers["Authorization"] = 'Basic '.base64_encode("{$this->user}:{$this->pass}"); 
          break;

        default:
          //this should never happen with login()'s validation, but just in case
          throw new SagException('Unknown auth type.');
          break;
      }
    }

    $buff = "$method $url HTTP/1.0\r\n";
    foreach($headers as $k => $v)
      if($k != 'Host' || $k != 'User-Agent' || $k != 'Content-Length' || $k != 'Content-Type')
        $buff .= "$k: $v\r\n";

    if($data)
      $buff .= "Content-Length: ".strlen($data)."\r\n"
              ."Content-Type: application/json\r\n\r\n"
              ."$data\r\n";
    else
      $buff .= "\r\n";

    // Send the packet.
    fwrite($sock, $buff);

    // Prepare the data structure to store the response.
    $response = new StdClass();
    $response->headers = new StdClass();
    $response->body = '';

    // Read in the response.
    $isHeader = true; //whether or not we're reading the HTTP headers or data

    while(!feof($sock))
    {
      $line = fgets($sock);

      if($isHeader)
      {
        $line = trim($line);

        if(empty($line))
          $isHeader = false; //the delim blank line
        else
        {
          if(!isset($response->headers->_HTTP))
          { 
            //the first header line is always the HTTP info
            $response->headers->_HTTP->raw = $line;

            if(preg_match('(^HTTP/(?P<version>\d+\.\d+)\s+(?P<status>\d+))S', $line, $match))
            {
              $response->headers->_HTTP->version = $match['version'];
              $response->headers->_HTTP->status = $match['status'];
            }
            else
              throw new SagException('There was a problem while handling the HTTP protocol.'); //whoops!
          }
          else
          {
            $line = explode(':', $line, 2);
            $response->headers->$line[0] = $line[1];
          }
        }
      }
      else
        $response->body .= $line;
    }

    $json = json_decode($response->body);
    if(!empty($json->error))
      throw new SagCouchException("{$json->error} ({$json->reason})", $response->headers->_HTTP->status);

    $response->body = ($this->decodeResp) ? $json : $response->body;

    return $response;
  }
}
?>