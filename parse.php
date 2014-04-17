#!/usr/bin/php -q
<?php
$status = 1;

if (isset($argv[1]) && file_exists($argv[1]) && ($buffer = file_get_contents($argv[1])) && 
    preg_match('/Copy:\s+([0-9\.]+)\s+([0-9\.]+)\s+([0-9\.]+)\s+([0-9\.]+)\s/msU', $buffer, $copy) && 
    preg_match('/Scale:\s+([0-9\.]+)\s+([0-9\.]+)\s+([0-9\.]+)\s+([0-9\.]+)\s/msU', $buffer, $scale) && 
    preg_match('/Add:\s+([0-9\.]+)\s+([0-9\.]+)\s+([0-9\.]+)\s+([0-9\.]+)\s/msU', $buffer, $add) && 
    preg_match('/Triad:\s+([0-9\.]+)\s+([0-9\.]+)\s+([0-9\.]+)\s+([0-9\.]+)\s/msU', $buffer, $triad)) {
  if (preg_match('/Array size\s+=\s+([0-9]+)\s/', $buffer, $m)) printf("array_size=%d\n", $m[1]*1);
  if (preg_match('/Memory per array\s+=\s+([0-9\.]+)\s/', $buffer, $m)) printf("memory_per_array=%s\n", $m[1]*1);
  if (preg_match('/Total memory required\s+=\s+([0-9\.]+)\s/', $buffer, $m)) printf("memory_required=%s\n", $m[1]*1);
  printf("stream_copy=%s\n", $copy[1]*1);
  printf("stream_copy_avg_time=%s\n", $copy[2]*1);
  printf("stream_copy_min_time=%s\n", $copy[3]*1);
  printf("stream_copy_max_time=%s\n", $copy[4]*1);
  printf("stream_scale=%s\n", $scale[1]*1);
  printf("stream_scale_avg_time=%s\n", $scale[2]*1);
  printf("stream_scale_min_time=%s\n", $scale[3]*1);
  printf("stream_scale_max_time=%s\n", $scale[4]*1);
  printf("stream_add=%s\n", $add[1]*1);
  printf("stream_add_avg_time=%s\n", $add[2]*1);
  printf("stream_add_min_time=%s\n", $add[3]*1);
  printf("stream_add_max_time=%s\n", $add[4]*1);
  printf("stream_triad=%s\n", $triad[1]*1);
  printf("stream_triad_avg_time=%s\n", $triad[2]*1);
  printf("stream_triad_min_time=%s\n", $triad[3]*1);
  printf("stream_triad_max_time=%s\n", $triad[4]*1);
  $max_triad = $triad[1]*1;
  $max_triad_threads = 1;
  $max_threads = 1;
  if (preg_match_all('/Threads requested = ([0-9]+)\s/msU', $buffer, $threads) && 
      preg_match_all('/Triad:\s+([0-9\.]+)\s/msU', $buffer, $triad)) {
    $triad_threaded = array();
    foreach($threads[1] as $i => $t) {
      if (isset($triad[1][$i]) && is_numeric($tp = $triad[1][$i])) {
        $triad_threaded[$t] = $tp*1;
        $max_threads = $t;
        if ($triad_threaded[$t] > $max_triad) {
          $max_triad = $triad_threaded[$t];
          $max_triad_threads = $t;
        }
      }
    }
    foreach($triad_threaded as $threads => $triad) if ($threads > 1) printf("stream_triad%d=%s\n", $threads, $triad);
    printf("stream_max_threads=%d\n", $max_threads);
    printf("stream_max_triad=%s\n", $max_triad);
    printf("stream_max_triad_threads=%d\n", $max_triad_threads);
    // create gnuplot graph
    if (count($triad_threaded) > 1 && file_exists('/usr/bin/gnuplot')) {
      $gnuplot_input = 'gnuplot-input.txt';
      $gnuplot_cmds = 'gnuplot-cmds.txt';
      $fp = fopen($gnuplot_input, 'w');
      foreach($triad_threaded as $threads => $triad) fwrite($fp, sprintf("%d %s\n", $threads, $triad));
      fclose($fp);
      $fp = fopen($gnuplot_cmds, 'w');
      fwrite($fp, "set autoscale x\n");
      fwrite($fp, "set autoscale y\n");
      fwrite($fp, "set xlabel 'Threads'\n");
      fwrite($fp, "set ylabel 'Triad MB/s'\n");
      fwrite($fp, "set key right bottom\n");
      fwrite($fp, "set terminal png\n");
      fwrite($fp, "set output 'triad-graph.png'\n");
      fwrite($fp, sprintf("plot '%s' with lines title 'STREAM Memory Scaling'\n", $gnuplot_input));
      fclose($fp);
      exec(sprintf('gnuplot < %s > /dev/null 2>&1', $gnuplot_cmds));
      unlink($gnuplot_input);
      unlink($gnuplot_cmds);
    }
  }
}

exit($status);
?>