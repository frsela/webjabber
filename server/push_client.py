#!/usr/bin/python
# -*- coding: utf-8 -*-
# HTTP to XMPP Connector - PUSH Notification Client
# Fernando Rodríguez Sela, 2013

from Crypto.PublicKey import RSA
from Crypto.Hash import SHA256
from Crypto.Signature import PKCS1_v1_5
from time import time
import urllib
import urllib2

class push_client:
	def __init__(self):
		self.privateKey = '''
-----BEGIN RSA PRIVATE KEY-----
MIICXgIBAAKBgQDFW14SniwCfJS//oKxSHin/uC1P6IBHiIvYr2MmhBRcRy0juNJ
H8OVgviFKEV3ihHiTLUSj94mgflj9RxzQ/0XR8tzPywKHxSGw4Amf7jKF1ZshCUd
yrOi8cLfzdwIz1nPvDF4wwbi2fqseX5Y7YlYxfpFlx8GvbnYJHO/50QGkQIDAQAB
AoGBAIXi0hL3Uwvs0EzfsHHspE3zzyWmoZT4iGB1L/oumltlzP+A4Bg/gEPxsf9D
rrzF4hQPzddl2mNtUW7KXh6kRRFPq182djTLXtwweLnC/vZ4Bh870wy3fGOMJ5Ii
04kpfWQ1xruKGobn+RMiA38zzM03tVaVP+ylduPauFxnl+UxAkEA98Ea0jL5g5jf
nwpTzZN0xclpQf5yQr+rTtR8qQJSBBblmmoY42eUQRxvmkCGW7TcWlOfZhxNTKOy
LLKzW1B6twJBAMvs3ve7b4kymR6siLgJr79uzJHCYsgXMFCewK+V0OIeDb5+hFQJ
brBz81YYHLyN4yPYgAkgv0LFnv3Jh6EvYPcCQQDmSAvZAt5ezhJUbjH0q7FnQc1f
NNUZa7Qb4m84XFrFSE8DlsgpXpYzau3kz0LTLKmAH6fSLk4/BQxQdY02O/jDAkAB
WoIkXM8htv9DL9v8dLwA5khfU036jATbFCKtR65KQe7Pa+GO+T0N2McttB1EtyBh
1YcMCHach9lFT/ghfsIDAkEA7Vk5s+S8y9dLB+taTfETFpiiJk+M4votA2zln5Gp
+nfoveICNUmfREbzNLkGh1zhIWIa1e4xoZkmfi//18aSXg==
-----END RSA PRIVATE KEY-----
'''[1:-1]

		self.key = RSA.importKey(self.privateKey)
		self.signer = PKCS1_v1_5.new(self.key);
		self.counter = 0

	def signMessage(self,data):
		return "".join(
			["%02x" % ord(x)
			for x in tuple(self.signer.sign(SHA256.new(data)))]
		)

	def pushMessage(self,data,uri):
		self.counter += 1
#		notif = '{' + \
#			'"messageType": "notification",' + \
#			'"id": ' + str(self.counter) + ',' + \
#			'"message": "' + data + '",' + \
#			'"signature": "' + self.signMessage(data) + '",' + \
#			'"ttl": 1,' + \
#			'"timestamp": "' + str(time()) + '",' + \
#			'"priority": 2' + \
#			'}'
		notif = 'version='+str(int(time.mktime(time.gmtime())) + self.counter)
		print "Going to send push notification: " + notif + " to URL: " + uri
#		f = urllib.urlopen(uri, notif)
#		print f.read()
		opener = urllib2.build_opener(urllib2.HTTPHandler)
		request = urllib2.Request(uri, data=notif)
		request.get_method = lambda: 'PUT'
		url = opener.open(request)
