#encoding=utf-8
'''
Created on 2018-01-17
@note: 读取redis数据服务 读取客户端打进来的日志信息
@author: chenjh
'''

import sys,json,time,uuid
import redis
import threading
import pymongo

reload(sys)
sys.setdefaultencoding('utf-8')

IS_DEBUG = 1  #  1 测试环境  0-线上环境

if 1 == IS_DEBUG:
    redis_ip = '127.0.0.1'
    port = 6379
    password = '123456'
    mongodb_host = '127.0.0.1'
    mongodb_port = 27017
    mongodb_name = 'CreateCache'
    mongodb_user = ''
    mongodb_password = ''
    mongodb_table_name = 'mod_big_data'
else:
    redis_ip = '192.168.0.110'
    port = 6392
    password = 'mZ7TailMgwrj'
    mongodb_host = '127.0.0.1'
    mongodb_port = 30005
    mongodb_name = 'CreateCache'
    mongodb_user = 'CreateCache'
    mongodb_password = '1fxPctG9gukkv04x5usx'
    mongodb_table_name = 'mod_big_data'


class RedisServer(object):

    redis_ip = redis_ip
    port = port
    password = password

    def __init__(self):
        try:
            if password != '':
                pool = redis.ConnectionPool(host = redis_ip,password = password,port = port,db=0)
            else:
                pool = redis.ConnectionPool(host = redis_ip,port = port,db=0)
            self.conn = redis.StrictRedis(connection_pool = pool)
        except Exception as e:
            print e
            sys.exit(0)

    def lpush(self,queue_name,value):
        return self.conn.lpush(queue_name, value)

    def brpop(self,queue_name):
        return self.conn.brpop(queue_name)


class MongodbServer(object):
    def __init__(self):
        connection = pymongo.Connection(
            mongodb_host,
            mongodb_port
        )
        db = connection[mongodb_name]
        if mongodb_user:
            db.authenticate(mongodb_user,mongodb_password)
        self.collection = db[mongodb_table_name]

    def process_item(self, item):
        try:
            for data in item:
                if not data:
                    raise
            self.collection.update({'id': item['id']}, dict(item), upsert=True)
        except Exception, e:
            raise e


Thread_Count = 2  # 线程数量

class readLogs(object):

    def __init__(self):
        self.myRedisObj = RedisServer()
        self.myMongodbObj = MongodbServer()
        self.redis_list = 'dsj'

    def run(self):
        i = 0
        while True:
            try:
                data = self.myRedisObj.brpop(self.redis_list)
                if data:
                    if i == 5:
                        insert_data = {}
                        insert_data['id'] = uuid.uuid4().get_hex()
                        insert_data['create_time'] = int(time.time())
                        insert_data['json_data'] = json.loads(data[1])
                        self.myMongodbObj.process_item(insert_data)
                        i = 0
                    else:
                        i = i + 1
                else:
                    time.sleep(10)
            except Exception as e:
                print e


class MyThread(threading.Thread):

    def __init__(self, read_logs):
        super(MyThread, self).__init__()  #注意：一定要显式的调用父类的初始化函数。
        self.read_logs = read_logs

    def run(self):  #定义每个线程要运行的函数
        try:
            self.read_logs.run()
        except  Exception as e:
            print time.strftime("%Y-%m-%d %H:%M:%S", time.localtime(time.time())),str(e)


if __name__ == '__main__':

    thread_list = []    #线程存放列表

    for i in xrange(Thread_Count):
       t = MyThread(readLogs())
       t.setDaemon(True)
       thread_list.append(t)

    for t in thread_list:
       t.start()

    for t in thread_list:
        t.join()
