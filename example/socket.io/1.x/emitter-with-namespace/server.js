var server     = require('http').createServer(),
    io         = require('socket.io')(server),
    logger     = require('winston'),
    port       = 1337;

// Logger config
logger.remove(logger.transports.Console);
logger.add(logger.transports.Console, { colorize: true, timestamp: true });
logger.info('SocketIO > listening on port ' + port);

io.of('/namespace').on('connection', function (socket){
    var nb = 0;

    logger.info('SocketIO /namespace > Connected socket ' + socket.id);

    socket.on('broadcast', function (message) {
        ++nb;
        logger.info('ElephantIO /namespace broadcast > ' + JSON.stringify(message));
    });

    socket.on('disconnect', function () {
        logger.info('SocketIO /namespace : Received ' + nb + ' messages');
        logger.info('SocketIO /namespace > Disconnected socket ' + socket.id);
    });
});

io.of('/namespace2').on('connection', function (socket){
    var nb = 0;

    logger.info('SocketIO /namespace2 > Connected socket ' + socket.id);

    socket.on('broadcast', function (message) {
        ++nb;
        logger.info('ElephantIO /namespace2 broadcast > ' + JSON.stringify(message));
    });

    socket.on('disconnect', function () {
        logger.info('SocketIO /namespace2 : Received ' + nb + ' messages');
        logger.info('SocketIO /namespace2 > Disconnected socket ' + socket.id);
    });
});

server.listen(port);

