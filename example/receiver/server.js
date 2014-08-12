var server     = require('http').createServer(),
    io         = require('socket.io')(server),
    logger     = require('winston'),
    port       = 1337;

// Logger config
logger.remove(logger.transports.Console);
logger.add(logger.transports.Console, { colorize: true, timestamp: true });
logger.info('SocketIO > listening on port ' + port);

io.on('connection', function (socket){
    logger.info('SocketIO > Connected socket ' + socket.id);

    socket.on('disconnect', function () {
        logger.info('SocketIO > Disconnected socket ' + socket.id);
    });
});

server.listen(port);

