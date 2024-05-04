# Step
1. Run Zipkin server
    download zipkin-server
    run zipkin
```
    cd /path/to/downloaded/zipkin.jar/
    java -jar zipkin-server-3.0.0-rc0-exec.jar
```
    open new browser tab and goto http://localhost:9411
2. Run RabbitMQ-server
    download and install rabbitmq
    run
```
    brew service start rabbitmq
``` 
3. Run App
    configure ENV in index.php and dispatch.php
```
    cd /path/to/app/directory/
    composer install
``` 
run dispatch.php as rabbitmq consumer
start new terminal
```
    php dispatch.php
``` 
4. Evaluate
open new browser tab and goto application base url
-> notice the result from browser 
-> notice the result of dispatch terminal

-> goto zipkin browser tab, explore 'Find a trace' and 'Dependencies'

