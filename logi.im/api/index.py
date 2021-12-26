import sys
import json
import time
from datetime import datetime

import requests
from biz import sentence, friend

CONF_PATH = 'asset/data/bundle.json'
TODAY = datetime.today().strftime('%Y-%m-%d')


class Bundle:
    def __init__(self):
        self.sentences = sentence.Api().pure_data()
        self.friends = friend.FriendLinkDoctor().check_boby()

    def save(self):
        with open(CONF_PATH, 'w') as f:
            json.dump({
                'code': 0,
                'msg': 'success',
                'date': TODAY,
                'support_url': 'https://logi.im',
                'sentences': self.sentences,
                'friends': self.friends
            }, f)

    @staticmethod
    def refresh():
        api = 'https://{}.jsdelivr.net/npm/logicdn/logi.im/api/asset/data/bundle.json'
        for _ in range(5):
            try:
                purge = requests.get(api.format('purge')).json()
                data = requests.get(api.format('cdn')).json()
                print(purge.get('status'), data['date'])
                if data['date'] == TODAY:
                    return
            except Exception:
                pass
            time.sleep(60)


if __name__ == '__main__':
    if len(sys.argv) != 1 and sys.argv[1] == 'r':
        Bundle.refresh()
    else:
        Bundle().save()
