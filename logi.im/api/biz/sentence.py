#!/usr/bin/env python3

import re
import json
import time
import random
from datetime import datetime
from urllib.parse import urlencode
from urllib.request import Request, urlopen

DATA_PATH = 'asset/data/sentences.json'
iPhone = 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1'


class Sentence(object):
    def __init__(self, name, url, data=None, headers={}, body_type='json', body_schema=None):
        self.name = name
        self.body_type = body_type
        self.body_schema = body_schema

        if 'User-Agent' not in headers:
            headers['User-Agent'] = iPhone

        if data:
            data = urlencode(data).encode('ascii')

        req = Request(url, data, headers)
        with urlopen(req) as resp:
            self.body = resp.read().decode('utf-8')

        self.data = []
        if body_type == 'html':
            self.from_html()
        else:
            self.from_json()

        self.format()

    def find_first(self, reg):
        rec = re.compile(reg, re.DOTALL)
        ret = rec.findall(self.body)
        if ret:
            return ret[0]

    def from_html(self):
        for s in self.body_schema:
            self.data.append(self.find_first(s))

    def from_json(self):
        d = json.loads(self.body)
        for s in self.body_schema:
            t = d

            while isinstance(s, list) and len(s) > 1:
                d = d[s[0]]
                s = s[1:]
                if len(s) == 1:
                    s = s[0]

            self.data.append(d[s])
            d = t

    def format(self):
        source = None
        if len(self.data) == 3:
            source = self.data[2]

        self.data = {
            "api": self.name,
            'sentence': self.data[0],
            'translation': self.data[1],
            'source': source
        }


class Api(object):
    def __init__(self):
        for _ in range(3):
            try:
                self.retrieve()
                break
            except Exception:
                time.sleep(random.randint(1, 3))
                pass

    def retrieve(self):
        self.sentences = [
            Sentence('hici',
                     'http://m.dict.cn/daily.php',
                     body_type='html',
                     body_schema=[
                         r'<div class="daily-cc">.+?<p.+?>(.+?)</p>',
                         r'<div class="daily-cc-ch">(.+?)</div>',
                         r'<div class="daily-cc-auth">—(.+?)</div>'
                     ]),
            Sentence('shanbay',
                     'https://apiv3.shanbay.com/weapps/dailyquote/quote/',
                     body_schema=[
                         'content',
                         'translation',
                         'author'
                     ]),
            Sentence('ciba',
                     'http://sentence.iciba.com/?c=dailysentence&m=getTodaySentence',
                     body_schema=[
                         'content',
                         'note'
                     ]),
            Sentence('youdao',
                     'http://dict.youdao.com/infoline/style/cardList?style=daily&client=mobile',
                     body_schema=[
                         [0, 'title'],
                         [0, 'summary'],
                         [0, 'source']
                     ]),
            Sentence('eudic',
                     'https://api.frdic.com/api/v2/appsupport/DictMobileStartupContent',
                     headers={
                         'Authorization': 'QYN eyJ1c2VyaWQiOiIiLCJ0b2tlbiI6IiIsInZfZGljdCI6ZmFsc2UsInVybHNpZ24iOiJVdzVJbWZaaVZaWVpTTVZhU3Y3cEg1TVhlbTQ9IiwidmYiOjAsInQiOiJBQklNVFU1T1RZME5EUTBNQT09IiwiZmwiOjAsImxjIjowfQ==',
                         'User-Agent': '/eusoft_eudic_en_android/7.5.0/cdb00fcf112cf5a6///'
                     },
                     body_schema=[
                         ['sentence', 'line'],
                         ['sentence', 'linecn']
                     ]),
        ]

    def save(self):
        with open(DATA_PATH, 'w') as f:
            f.write(self.format())

    def format(self):
        return json.dumps({
            'code': 0,
            'msg': 'success',
            'date': datetime.today().strftime('%Y-%m-%d'),
            'support_url': 'https://logi.im',
            'sentences': list(s.data for s in self.sentences)
        })

    def pure_data(self):
        return list(s.data for s in self.sentences)


if __name__ == '__main__':
    Api().save()
