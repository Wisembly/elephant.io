var server     = require('http').createServer(),
    io         = require('socket.io')(server),
    logger     = require('winston'),
    port       = 1337;

// Logger config
logger.remove(logger.transports.Console);
logger.add(logger.transports.Console, { colorize: true, timestamp: true });
logger.info('SocketIO > listening on port ' + port);

// Stored tokens
var tokens = {};

// Stored users
var users = {};

// set up initialization and authorization method
io.use(function (socket, next) {
    var auth = socket.request.headers.authorization;
    var user = socket.request.headers.user;
    if(auth && user) {
        const token = auth.replace("Bearer ", "");
        logger.info("auth token", token);
        // do some security check with token
        // ...
        // store token and bind with specific socket id
        if (!tokens[token] && !users[token]) {
            tokens[token] = socket.id;
            users[token] = user;
        }

        return next();
    }
    else{
        return next(new Error("no authorization header"));
    }
});

io.on('connection', function (socket){
    var nb = 0;

    logger.info('SocketIO > Connected socket ' + socket.id);
    logger.info("X-My-Header", socket.handshake.headers['x-my-header']);

    socket.on('private_chat_message', function (message) {
        ++nb;
        logger.info('ElephantIO private_chat_message > ' + JSON.stringify(message));

        if (!message['token']) {
            logger.info('ElephantIO private_chat_message > ' + "Token is missed.");
        }

        if (!tokens[message['token']]) {
            logger.info('ElephantIO private_chat_message > ' + "Token is invalid");
        }

        var user = users[message['token']];

        if(!user) {
            logger.info('ElephantIO private_chat_message > ' + 'Sorry. I don\'t remember you.');
        } else if (message['message'].indexOf('remember') !== -1) {
            logger.info('ElephantIO private_chat_message > ' + 'I remember you, ' + user);
        } else {
            logger.info('ElephantIO private_chat_message > ' + 'I am fine, ' + user);
        }
    });

    socket.on('disconnect', function () {
        logger.info('SocketIO : Received ' + nb + ' messages');
        logger.info('SocketIO > Disconnected socket ' + socket.id);
    });
});

server.listen(port);
