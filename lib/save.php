#!/usr/bin/php -q
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
 * saves results based on the arguments defined in ../run.sh
 */
require_once(dirname(__FILE__) . '/StreamTest.php');
require_once(dirname(__FILE__) . '/save/BenchmarkDb.php');
$status = 1;
$args = parse_args(array('v' => 'verbose'));

// get result directories => each directory stores 1 iteration of results
$dirs = array();
$dir = count($argv) > 1 && is_dir($argv[count($argv) - 1]) ? $argv[count($argv) - 1] : trim(shell_exec('pwd'));
if (is_dir(sprintf('%s/1', $dir))) {
  $i = 1;
  while(is_dir($sdir = sprintf('%s/%d', $dir, $i++))) $dirs[] = $sdir;
}
else $dirs[] = $dir;

if ($db =& BenchmarkDb::getDb()) {
  // get results from each directory
  foreach($dirs as $i => $dir) {
    $test = new StreamTest($dir);
    $iteration = $i + 1;
    if ($results = $test->getResults()) {
      $results['iteration'] = $iteration;
      print_msg(sprintf('Saving results in directory %s', $dir), isset($args['verbose']), __FILE__, __LINE__);
      // save report.pdf and report.zip
      foreach(array('triad-graph.png') as $file) {
        $file = sprintf('%s/%s', $dir, $file);
        if (file_exists($file)) {
          $col = 'triad_png';
          $saved = $db->saveArtifact($file, $col);
          if ($saved) print_msg(sprintf('Saved %s successfully', basename($file)), isset($args['verbose']), __FILE__, __LINE__);
          else if ($saved === NULL) print_msg(sprintf('Unable to save %s', basename($file)), isset($args['verbose']), __FILE__, __LINE__, TRUE);
        }
        else print_msg(sprintf('%s will not be saved because it does not exist', basename($file)), isset($args['verbose']), __FILE__, __LINE__);
      }
      if ($db->addRow('stream', $results)) print_msg(sprintf('Successfully saved test results'), isset($args['verbose']), __FILE__, __LINE__);
      else print_msg(sprintf('Failed to save test results'), isset($args['verbose']), __FILE__, __LINE__, TRUE);
    }
    else print_msg(sprintf('Unable to save results in directory %s - are result files present?', $dir), isset($args['verbose']), __FILE__, __LINE__, TRUE);
  }
  
  // finalize saving of results
  if ($db->save()) {
    print_msg(sprintf('Successfully saved test results from directory %s', $dir), isset($args['verbose']), __FILE__, __LINE__);
    $status = 0;
  }
  else {
    print_msg(sprintf('Unable to save test results from directory %s', $dir), isset($args['verbose']), __FILE__, __LINE__, TRUE);
    $status = 1;
  }
}

exit($status);
?>
