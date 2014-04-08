var io = require('socket.io').listen(8000);

io.sockets.on('connection', function (socket) {
  console.log('user connected');

  socket.on('ping', function (data) {
    console.log('Ping received with data: ' + data);
    socket.emit('pong', '42');
  });
});