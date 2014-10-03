var server     = require('http').createServer(),
    io         = require('socket.io')(server),
    logger     = require('winston'),
    port       = 1337;

// Logger config
logger.remove(logger.transports.Console);
logger.add(logger.transports.Console, { colorize: true, timestamp: true });
logger.info('SocketIO > listening on port ' + port);

io.of('/test').on('connection', function (socket){
    logger.info('SocketIO > Connected to namespace /test socket ' + socket.id);
    socket.on('broadcast', function(data){
        logger.info('SocketIO > Broadcasted to namespace /test message ' + JSON.stringify(data));   
    });
});

io.on('connection', function (socket){
   logger.info('SocketIO > Connected to namespace / socket ' + socket.id);
   socket.on('broadcast', function(data){
        logger.info('SocketIO > Broadcasted to namespace / message ' + JSON.stringify(data));   
   });
});

server.listen(port);

