#!/usr/bin/python
# -*- coding: utf-8 -*-
# HTTP to XMPP Connector - XMPP Client
# Fernando Rodríguez Sela, 2008-2012

import xmpp
import datetime
import string

# Almacén de mensajes recibidos
RcvMsg = dict()
# Almacén de Rosters
Rosters = dict()

# Función de callback para recepción de mensajes XMPP
def MessageCallBack(conn,mess):
	""" Se llama cuando se recibe mensaje nuevo """
	# Hora y fecha de recepción del mensaje
	Ahora = datetime.datetime.today()

	print "--------------------------------------"
	print " MENSAJE"
	print "--------------------------------------"
	TO = mess.getTo().getStripped()
	print "Para.....: %s"%TO
	print "De.......: %s"%mess.getFrom().getStripped()
	print "Fecha....: %s"%Ahora.date().isoformat()
	print "Hora.....: %s"%Ahora.time().isoformat()
	print "Mensaje..: %s"%mess.getBody()

	# Si es la primera vez que recibimos mensajes para este JID, creamos entrada en diccionario
	if RcvMsg.keys().count(TO) == 0:
		RcvMsg[TO] = []

	# Almacenamos mensaje en memoria hasta que nos lo soliciten
	msg = "{ timestamp: {"
	msg += "date: '" + Ahora.date().isoformat() + "',"
	msg += "time: '" + Ahora.time().isoformat() + "'},"
	msg += "from: '" + mess.getFrom().getStripped() + "',"
	if mess.getBody():
		msg += "body: '" + mess.getBody() + "'}"
	else:
		msg += "body: ''}"
	RcvMsg[TO].append(msg)

	print "--------------------------------------"

# Función de callback para recepción de presencia XMPP
def PresenceCallBack(conn,presence_node):
	""" Se llama cuando cambia la presencia """
	print "--------------------------------------"
	print " PRESENCIA"
	print "--------------------------------------"
	print presence_node

	# Sólo vamos a diferenciar online/offline, con lo que si entre los atributos de "presence" aparece 'type="unavailable"' es OFFLINE, resto, ONLINE
	if presence_node.getType() == "unavailable":
		Rosters[presence_node.getTo().getStripped()][presence_node.getFrom().getStripped()]['presence'] = "offline"
	else:
		Rosters[presence_node.getTo().getStripped()][presence_node.getFrom().getStripped()]['presence'] = "online"

	# A modo depuración, volcamos el estado del roster
	for roster in Rosters[presence_node.getTo().getStripped()].keys():
		print roster
		print "Estado %s"%Rosters[presence_node.getTo().getStripped()][roster]['presence']
	print "--------------------------------------"

# Función de callback para recepción de IQ (Info/Query) XMPP
def iqCallBack(conn,iq_node):
	""" Se llama cuando hay que procesar consultas "get" de un namespace propio """
	print "--------------------------------------"
	print " IQ"
	print "--------------------------------------"
	print iq_node
	print "--------------------------------------"

# Clase de gestión de cliente XMPP
class XMPPClient:
	def __init__(self):
		self.conectado = 0

	def __del__(self):
		self.Desconectar()
		print "Cliente destruido"

	# Conectar a servidor JABBER
	def Conectar(self, jid_uri, password, port = 443):
		self.jid = xmpp.JID(jid_uri)
		user, server = self.jid.getNode(), self.jid.getDomain()

		# Debug level (Change the comment to enable/disable debug traces)
		##debug = []
		debug = ['nodebuilder', 'dispatcher', 'gen_auth', 'SASL_auth', 'bind', 'socket', 'CONNECTproxy', 'TLS', 'roster', 'browser', 'ibb']

		# Creamos cliente XMPP
		self.conn = xmpp.Client(server, port, debug)

		# Nos conectamos al servidor JABBER
		conres = self.conn.connect()
		if not conres:
			print "No puedo conectar con el servidor %s!"%server
			return
		if conres <> 'tls':
			print "Atención: No se puede establecer conexión segura - Fallo de TLS!"

		# Nos autenticamos
		authres = self.conn.auth(user,password,"movistar WebJabber")
		if not authres:
			print "No se puede autenticar en %s. Verificar usuario/password"%server
			return
		if authres <> 'sasl':
			print "Atención: Incapaz de autorizar con SASL en %s. Se utiliza viejo método de autenticación!"%server

		# Inicializamos diccionario de mensajes recibidos
		RcvMsg[self.jid] = [];

		# Obtenemos el Roster (contactos amigos) de este cliente
		Rosters[self.jid.getStripped()] = self.conn.getRoster();
		# Les añadimos la clave de presencia al diccionario de cada contacto
		for i in Rosters[self.jid.getStripped()].keys():
			Rosters[self.jid.getStripped()][i]['presence'] = "offline"

		# Registramos funciones de callback
		self.conn.RegisterHandler('message',MessageCallBack)
		self.conn.RegisterHandler('presence',PresenceCallBack)
		self.conn.RegisterHandler('iq',iqCallBack)

		# Enviamos presencia inicial
		self.conn.sendInitPresence()

		# Si llegamos aquí, es que estamos conectados
		self.conectado = 1

	def Desconectar(self):
		if(self.conectado == 1):
			try:
				self.conn.disconnect()
				# Liberamos diccionario de mensajes recibidos
				RcvMsg[self.jid] = [];
			except:
				pass
			conectado = 0

	def EnviarMensaje(self,to,mensaje):
		if(self.conectado == 1):
			self.conn.send(xmpp.Message(to,mensaje))

	def RecibirMensajes(self):
		if RcvMsg.keys().count(self.jid) == 0:
			Mensajes = []
		Mensajes = RcvMsg[self.jid]
		RcvMsg[self.jid] = []
		return Mensajes

	def RecibirPresencia(self):
		Estados = "["
		first = True
		for r in Rosters[self.jid.getStripped()].keys():
			if first:
				first = False
			else:
				Estados += ","
			Estados += "{ roster: '" + r + "',"
			Estados += "presence: '" + Rosters[self.jid.getStripped()][r]['presence'] + "'}"
		Estados += "]"
		return Estados

	def ProcesarDatos(self):
		if(self.conectado == 1):
			self.conn.Process(1)
