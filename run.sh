#!/bin/bash
# Copyright 2014 CloudHarmony Inc.
# 
# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at
# 
#     http://www.apache.org/licenses/LICENSE-2.0
# 
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License.


if [ "$1" == "-h" ] || [ "$1" == "--help" ] ; then
  cat << EOF
Usage: run.sh [options]

Uses the STREAM benchmark and stream-scaling automation scripts to measure 
memory performance. STREAM is a simple synthetic benchmark program that 
measures sustainable memory bandwidth. The STREAM benchmark consists of four 
tests:

Copy -  measures transfer rates in the absence of arithmetic
Scale - adds a simple arithmetic operation
Add -   adds a third operand to allow multiple load/store ports on vector 
        machines to be tested
Triad - allows chained/overlapped/fused multiply/add operations

stream-scaling detects the number of CPUs in the system and how large each of 
their caches are. It then downloads STREAM, compiles it, and runs it with an 
array size large enough to not fit into cache. The number of threads is varied 
from 1 to the total number of cores in the server, so that you can see how 
memory speed scales as cores involved increase. stream-scaling is available 
here:

https://github.com/gregs1104/stream-scaling


TESTING PARAMETERS
Test behavior is fixed, but you may specify the following optional meta 
attributes. These will be included with the results (see save.sh)

--meta_compute_service      The name of the compute service this test pertains
                            to. May also be specified using the environment 
                            variable bm_compute_service
                            
--meta_compute_service_id   The id of the compute service this test pertains
                            to. Added to saved results. May also be specified 
                            using the environment variable bm_compute_service_id
                            
--meta_cpu                  CPU descriptor - if not specified, it will be set 
                            using the 'model name' attribute in /proc/cpuinfo
                            
--meta_instance_id          The compute service instance type this test pertains 
                            to (e.g. c3.xlarge). May also be specified using 
                            the environment variable bm_instance_id
                            
--meta_memory               Memory descriptor - if not specified, the system
                            memory size will be used
                            
--meta_os                   Operating system descriptor - if not specified, 
                            it will be taken from the first line of /etc/issue
                            
--meta_provider             The name of the cloud provider this test pertains
                            to. May also be specified using the environment 
                            variable bm_provider
                            
--meta_provider_id          The id of the cloud provider this test pertains
                            to. May also be specified using the environment 
                            variable bm_provider_id
                            
--meta_region               The compute service region this test pertains to. 
                            May also be specified using the environment 
                            variable bm_region
                            
--meta_resource_id          An optional benchmark resource identifiers. May 
                            also be specified using the environment variable 
                            bm_resource_id
                            
--meta_run_id               An optional benchmark run identifiers. May also be 
                            specified using the environment variable bm_run_id
                            
--meta_storage_config       Storage configuration descriptor. May also be 
                            specified using the environment variable 
                            bm_storage_config
                            
--meta_test_id              Identifier for the test. May also be specified 
                            using the environment variable bm_test_id
                            
--output                    The output directory to use for writing test data 
                            (results log and triad results graphs). If not 
                            specified, the current working directory will be 
                            used
                            
--verbose                   Show verbose output - warning: this may produce a 
                            lot of output


DEPENDENCIES
This benchmark has the following dependencies. gnuplot is optional. If not 
present, the triad results graphs will not be generated.

  gcc         Used to compile STREAM
  
  gnuplot     Used to generate triad results graphs
  
  php         Used for automation and saving of results


TEST ARTIFACTS
This benchmark generates the following artifacts:

triad-graph.png    A gnuplot graph generated from threaded triad results


SAVE SCHEMA
The following columns are included in CSV files/tables generated by save.sh. 
Indexed MySQL/PostgreSQL columns are identified by *. Columns without 
descriptions are documented as runtime parameters above. Data types and 
indexing used is documented in save/schema/*.json

array_size: [array size used for testing]
benchmark_version: [benchmark version]
iteration: [iteration number (used with incremental result directories)]
memory_per_array: [memory per array]
memory_required: [total memory required]
meta_compute_service
meta_compute_service_id*
meta_cpu: [CPU model info]
meta_cpu_cache: [CPU cache]
meta_cpu_cores: [# of CPU cores]
meta_cpu_speed: [CPU clock speed (MHz)]
meta_instance_id*
meta_hostname: [system under test (SUT) hostname]
meta_memory
meta_memory_gb: [memory in gigabytes]
meta_memory_mb: [memory in megabyets]
meta_os_info: [operating system name and version]
meta_provider
meta_provider_id*
meta_region*
meta_resource_id
meta_run_id
meta_storage_config*
meta_test_id*
stream_add: [single thread STREAM Add throughput (MB/sec)]
stream_add_avg_time: [average time for STREAM Add tests]
stream_add_min_time: [min time for STREAM Add tests]
stream_add_max_time: [max time for STREAM Add tests]
stream_copy: [single thread STREAM Copy throughput (MB/sec)]
stream_copy_avg_time: [average time for STREAM Copy tests]
stream_copy_min_time: [min time for STREAM Copy tests]
stream_copy_max_time: [max time for STREAM Copy tests]
stream_max_threads: [max number of threads that testing was performed for]
stream_max_triad: [max STREAM Triad throughput for any thread count (MB/sec)]
stream_max_triad_threads: [thread count where stream_max_triad was achieved]
stream_scale: [single thread STREAM Scale throughput (MB/sec)]
stream_scale_avg_time: [average time for STREAM Scale tests]
stream_scale_min_time: [min time for STREAM Scale tests]
stream_scale_max_time: [max time for STREAM Scale tests]
stream_triad: [single thread STREAM Triad throughput (MB/sec)]
stream_triad_avg_time: [average time for STREAM Triad tests]
stream_triad_min_time: [min time for STREAM Triad tests]
stream_triad_max_time: [max time for STREAM Triad tests]
stream_triad_threaded:  [multithreaded STREAM Triad throughput (MB/sec)]
stream_triad_threads: [number of threads used for the above metric]
test_started*: [when the test started]
test_stopped: [when the test ended]
triad_png: [URL to the threaded triad results graph (if --store option used)]


USAGE
# run 1 test iteration with some metadata
./run.sh --meta_compute_service_id aws:ec2 --meta_instance_id c3.xlarge --meta_region us-east-1 --meta_test_id aws-0914

# run 10 test iterations using a specific output directory
for i in {1..10}; do mkdir -p ~/stream-testing/$i; ./run.sh --output ~/stream-testing/$i; done


EXIT CODES:
  0 test successful
  1 test failed

EOF
  exit
elif [ -f "/usr/bin/php" ] && [ -f "/usr/bin/gcc" ]; then
  $( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )/lib/run.php $@
  exit $?
else
  echo "Error: missing dependency php-cli (/usr/bin/php), gcc (/usr/bin/gcc)"
  exit 1
fi
