#!/usr/bin/env python3

import json
import os
from datetime import datetime
from urllib.request import Request, urlopen

CONF_PATH = 'asset/data/festival.json'
api = {
    'holiday': 'http://api.comm.miui.com/holiday/holiday.jsp',
    'festival': 'http://pc.suishenyun.net/peacock/api/h5/festival'
}


def get_last(d, path):
    for p in path:
        d = d[p]
    return d


def get_json(url, schema):
    with urlopen(Request(url)) as resp:
        body = json.loads(resp.read().decode('utf-8'))

    result = None
    if callable(schema):
        result = schema(body)
    elif 'array' in schema:
        body = get_last(body, schema['array']['start'])
        result = []
        for every in body:
            elem = {}
            for k, v in schema['array']['every'].items():
                elem[k] = get_last(every, v)
            result.append(elem)

    return result


def save(y):
    festival = get_json(api['festival'], {
        'array': {
            'start': ['national_holiday', 'cn'],
            'every': {
                'date': ['date'],
                'name': ['name'],
            }
        }
    })

    holiday = get_json(api['holiday'], lambda d: list(
        filter(lambda e: e['year'] == y, d['holiday'])
    )[0])

    with open(CONF_PATH, 'w') as f:
        f.write(json.dumps({
            **holiday,
            'festival': festival,
        }))


if os.path.exists(CONF_PATH):
    with open(CONF_PATH) as f:
        data = json.loads(f.read())

    year = datetime.now().year
    if data['year'] != year:
        save(year)
else:
    save(datetime.now().year)
