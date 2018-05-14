#encoding=utf-8
'''
Created on 2018-01-17
@note: 企业微信端数据监控
       基于redis的订阅
@author: chenjh
'''

import sys,json,time
import urllib2
import requests
import ssl
import redis
import threading

reload(sys)
sys.setdefaultencoding('utf-8')

IS_DEBUG = 1  #  1 测试环境  0-线上环境

if 1 == IS_DEBUG:
    redis_ip = '127.0.0.1'
    port = 6379
    password = '123456'
else:
    redis_ip = '192.168.0.110'
    port = 6392
    password = 'mZ7TailMgwrj'

channel_list = ['monitor_message']


def send_weixin(title,content):
    print 'run'
    baseurl = "输入您企业微信监控项目的api接口地址"
    request = urllib2.Request(baseurl)
    response = urllib2.urlopen(request)
    ret = response.read().strip()
    dd = eval(ret)
    mytoken = dd["access_token"]
    url = "https://qyapi.weixin.qq.com/cgi-bin/message/send?access_token={0}".format(mytoken)
    payload = {
            "touser": "@all",   #@all
            "msgtype": "text",
            "agentid": "1000003",
            "text": {
               "content": "title:{0}\ncontent:{1}".format(title,content)
            },
            "safe": "0"
    }

    ret = requests.post(url, data=json.dumps(payload,ensure_ascii=False),verify=False)
    print ret


class RedisSubscriber(object):
    """
    Redis频道订阅辅助类
    """
    redis_ip = redis_ip
    port = port
    password = password

    def __init__(self, channel):
        self._pool = redis.ConnectionPool(host=self.redis_ip,port=self.port,password=self.password)
        self.conn = redis.StrictRedis(connection_pool=self._pool)
        self.channel = channel  # 定义频道名称

    def psubscribe(self):
        """
        订阅方法
        """
        pub = self.conn.pubsub()
        pub.psubscribe(self.channel)  # 同时订阅多个频道，要用psubscribe
        pub.listen()
        return pub

    def keep_alive(self):
        """
        保持客户端长连接
        """
        ka_thread = threading.Thread(target=self._ping)
        ka_thread.start()

    def _ping(self):
        """
        发个消息，刷存在感
        """
        while True:
            time.sleep(120)
            # 尝试向redis-server发一条消息
            if not self.conn.ping():
                print("oops~ redis-server get lost. call him back now!")
                del self._pool
                self._pool = redis.ConnectionPool(host=self.redis_ip,port=self.port)
                self.conn = redis.StrictRedis(connection_pool=self._pool)


if __name__ == '__main__':
    try:
        _create_unverified_https_context = ssl._create_unverified_context
    except AttributeError:
        # Legacy Python that doesn't verify HTTPS certificates by default
        pass
    else:
        # Handle target environment that doesn't support HTTPS verification
        ssl._create_default_https_context = _create_unverified_https_context

    subscriber = RedisSubscriber(channel_list)
    subscriber.keep_alive()
    redis_sub = subscriber.psubscribe()   # 调用订阅方法

    while True:
        try:
            dataInfo = redis_sub.parse_response(block=False, timeout=60) # 订阅的数据
            print dataInfo
            if dataInfo == None:
                continue
            if dataInfo[0] != 'psubscribe':
                dataInfo = json.loads(dataInfo[3])
                if dataInfo == None:
                    continue
                else:
                    title = dataInfo['msg_title']
                    content = dataInfo['msg_content']
                    send_weixin(title, content)
        except Exception as e:
            print e
            raise e