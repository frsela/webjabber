WebApp Jabber implementation
===

## Author:

- Fernando Rodríguez Sela (frsela @ tid . es)

## Introduction

### A bit of history

On 2008 I implemented a very simple HTTP2XMPP proxy server to allow WRT applications
(Web RunTime) to connect Jabber systems.

On that time, the implementation was only a fast prototype to explain an idea and
allowed us to create a little jabber client based on WRT technology supported by
Nokia handsets. This first release only worked by polling this proxy server to collect
received messages.

Now (2012/2013) I worked for the Firefox OS project, mainly for the PUSH notification
service, so I thought (and my boss remembered it to me :) that could be a great idea
to resurrect that old project and improve it to use our new notification server.

### Current implementation

The main objective of this new implementation is to develop a simple Jabber client
for the Firefox OS (so implement it as a WebApp) in order to demostrate the PUSH
implementation and also get a IM client for this new OS.

This new implementation avoid the old polling method by the use of the
notification server (https://github.com/telefonicaid/notification_server) so for
every message received a PUSH notification will be sent to the client.

## How it works

Jabber protocol needs to maintain connections opened but on the mobile world this
is a little issue (battery comsuption, network overload, ...) so the idea is to
maintain the connection opened on the server side and use a really simple HTTP API
to connect/disconnect the client (manage presence), send and receive messages,
and get the other contacts presence status.

So the server implementation will maintain a Jabber client connection for each
mobile connected to it and will offer a HTTP API to update the UI in the handset
side.

Instead maintain open connections or polling the server, the PUSH platform will
be used.

### Status

Currently, I'm only porting the server to remove MySQL dependencies used in my
old demo and PHP interface moving all the protocol to python.

### Security

On these first releases I didn't care about security (ie, passwords are sent on
GET method so they aren't protected by SSL layer - In a future version I'll send
it as a POST in the payload so it will be encrypted)

### Pending work

* Implement a WebApp client for Firefox OS
* Implement the python interface with the notification server
* Add a new parameter to manage the PUSH URL and use it on new messages
* Change the payload messages (response) to JSON objects.
* Improve security
* Translate methods

## API

Currently the server API is really simple:

### Connect a new Jabber ID (JID)

URI: http://server:port/connect?jid&pwd

Parameters:

* jid = JID identificator (normally as an e-mail address)
* pwd = Jabber password

### Disconnect a Jabber ID

URI: http://server:port/disconnect?jid

Parameters:

* jid = JID identificator (normally as an e-mail address)

### Send a Jabber message

URI: http://server:port/send?from&to&msg

Parameters:

* from = Origin JID
* to = Destination JID
* msg = Message to send

### Receive all pending messages

URI: http://server:port/receive?jid

Parameters:

* jid = My JID

### Receive my contacs presence status

URI: http://server:port/receivestatus?jid

Parameters:

* jid = My JID

### Dump debug information

URI: http://server:port/dumprcv