<?php
// Copyright 2014 CloudHarmony Inc.
// 
// Licensed under the Apache License, Version 2.0 (the "License");
// you may not use this file except in compliance with the License.
// You may obtain a copy of the License at
// 
//     http://www.apache.org/licenses/LICENSE-2.0
// 
// Unless required by applicable law or agreed to in writing, software
// distributed under the License is distributed on an "AS IS" BASIS,
// WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
// See the License for the specific language governing permissions and
// limitations under the License.


/**
 * Used to manage STREAM testing
 */
require_once(dirname(__FILE__) . '/util.php');
ini_set('memory_limit', '16m');
date_default_timezone_set('UTC');

class StreamTest {
  
  /**
   * name of the file where serializes options should be written to for given 
   * test iteration
   */
  const STREAM_TEST_OPTIONS_FILE_NAME = '.options';
  
  /**
   * name of the file stream scaling output is written to
   */
  const STREAM_TEST_TEST_FILE_NAME = 'stream-scaling.log';
  
  /**
   * optional results directory object was instantiated for
   */
  private $dir;
  
  /**
   * run options
   */
  private $options;
  
  
  /**
   * constructor
   * @param string $dir optional results directory object is being instantiated
   * for. If set, runtime parameters will be pulled from the .options file. Do
   * not set when running a test
   */
  public function StreamTest($dir=NULL) {
    $this->dir = $dir;
  }
  
  /**
   * writes test results and finalizes testing
   * @return boolean
   */
  private function endTest() {
    $ended = FALSE;
    $dir = $this->options['output'];
    
    // add test stop time
    $this->options['test_stopped'] = date('Y-m-d H:i:s');
    
    // serialize options
    $ofile = sprintf('%s/%s', $dir, self::STREAM_TEST_OPTIONS_FILE_NAME);
    if (is_dir($dir) && is_writable($dir)) {
      $fp = fopen($ofile, 'w');
      fwrite($fp, serialize($this->options));
      fclose($fp);
      $ended = TRUE;
    }
    // generate gnuplot graph
    exec(sprintf('cd %s;%s/parse.php %s/%s', $dir, dirname(__FILE__), $dir, self::STREAM_TEST_TEST_FILE_NAME));
    
    return $ended;
  }
  
  /**
   * returns results from testing as a hash of key/value pairs
   * @return array
   */
  public function getResults() {
    $results = NULL;
    if (isset($this->dir) && is_dir($this->dir) && file_exists($ofile = sprintf('%s/%s', $this->dir, self::STREAM_TEST_TEST_FILE_NAME))) {
      foreach($this->getRunOptions() as $key => $val) {
        if (preg_match('/^meta_/', $key) || preg_match('/^test_/', $key)) $results[$key] = $val;
      }
      if ($handle = popen(sprintf('%s/parse.php %s', dirname(__FILE__), $ofile), 'r')) {
        while(!feof($handle) && ($line = fgets($handle))) {
          if (preg_match('/^([a-z][^=]+)=(.*)$/', $line, $m)) $results[$m[1]] = $m[2];
        }
        fclose($handle);
      }
    }
    return $results;
  }
  
  /**
   * returns run options represents as a hash
   * @return array
   */
  public function getRunOptions() {
    if (!isset($this->options)) {
      if ($this->dir) $this->options = self::getSerializedOptions($this->dir);
      else {
        // default run argument values
        $sysInfo = get_sys_info();
        $defaults = array(
          'meta_compute_service' => 'Not Specified',
          'meta_cpu' => $sysInfo['cpu'],
          'meta_instance_id' => 'Not Specified',
          'meta_memory' => $sysInfo['memory_gb'] > 0 ? $sysInfo['memory_gb'] . ' GB' : $sysInfo['memory_mb'] . ' MB',
          'meta_os' => $sysInfo['os_info'],
          'meta_provider' => 'Not Specified',
          'meta_storage_config' => 'Not Specified',
          'output' => trim(shell_exec('pwd'))
        );
        $opts = array(
          'meta_compute_service:',
          'meta_compute_service_id:',
          'meta_cpu:',
          'meta_instance_id:',
          'meta_memory:',
          'meta_os:',
          'meta_provider:',
          'meta_provider_id:',
          'meta_region:',
          'meta_resource_id:',
          'meta_run_id:',
          'meta_storage_config:',
          'meta_test_id:',
          'output:',
          'v' => 'verbose'
        );
        $this->options = parse_args($opts); 
        foreach($defaults as $key => $val) {
          if (!isset($this->options[$key])) $this->options[$key] = $val;
        } 
      }
    }
    return $this->options;
  }
  
  /**
   * returns options from the serialized file where they are written when a 
   * test completes
   * @param string $dir the directory where results were written to
   * @return array
   */
  public static function getSerializedOptions($dir) {
    return unserialize(file_get_contents(sprintf('%s/%s', $dir, self::STREAM_TEST_OPTIONS_FILE_NAME)));
  }
  
  /**
   * initiates stream scaling testing. returns TRUE on success, FALSE otherwise
   * @return boolean
   */
  public function test() {
    $success = FALSE;
    
    $this->options['test_started'] = date('Y-m-d H:i:s');
    $handle = popen($cmd = sprintf('cd %s;%s/stream-scaling/stream-scaling 2>/dev/null', $this->options['output'], dirname(dirname(__FILE__))), 'r');
    if ($handle && ($line = fgets($handle))) {
      if ($fp = fopen($ofile = sprintf('%s/%s', $this->options['output'], self::STREAM_TEST_TEST_FILE_NAME), 'w')) {
        $success = TRUE;
        printf("%s", $line);
        fwrite($fp, sprintf("%s", $line));
        while(!feof($handle)) {
          $line = fgets($handle);
          printf("%s", $line);
          fwrite($fp, sprintf("%s", $line));
        }
        fclose($fp);
      }
      else print_msg(sprintf('Unable to open file pointer to %s', $ofile), isset($this->options['verbose']), __FILE__, __LINE__, TRUE);
      fclose($handle);
      exec(sprintf('rm -f %s/stream', $this->options['output']));
      exec(sprintf('rm -f %s/stream.c', $this->options['output']));
      $this->endTest();
    }
    else print_msg(sprintf('Unable initiate command %s', $cmd), isset($this->options['verbose']), __FILE__, __LINE__, TRUE);
    
    return $success;
  }
  
  /**
   * validate run options. returns an array populated with error messages 
   * indexed by the argument name. If options are valid, the array returned
   * will be empty
   * @return array
   */
  public function validateRunOptions() {
    $validate = array(
      'output' => array('write' => TRUE),
    );
    return validate_options($this->getRunOptions(), $validate);
  }
  
}
?>
