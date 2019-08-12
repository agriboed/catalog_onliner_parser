import requests
import os
import time
from pyquery import PyQuery
from PIL import Image
from io import BytesIO
import json
import sys
import constants

data = []
makeCMSRequest = False
processImages = False
watermark = None


def message(msg):
    time_string = time.strftime("%H:%M:%S", time.localtime())
    print(time_string, msg)


if 'cms' in sys.argv:
    message('Requests to CMS enabled')
    makeCMSRequest = True

if 'image' in sys.argv:
    message('Image processing enabled. Opening ' + constants.WATERMARK)
    processImages = True
    watermark = Image.open(constants.WATERMARK)

with open(constants.CSV_FILE) as fp:
    line = fp.readline()
    cnt = 1
    while line:
        item = line.strip().split(';')

        data.append({
            'category': item[0],
            'manufacturer': item[1],
            'id': item[2],
            'name': item[3],
            'url': item[4],
            'price': float(item[5]),
        })

        line = fp.readline()
        cnt += 1

for value in data:
    result_file = constants.RESULT_DATA_PATH + str(value['id']) + '.json'

    if os.path.isfile(result_file):
        continue

    images_path = constants.RESULT_IMAGES_PATH + str(value['id']) + '/'
    url = value['url'].split('/')
    body = requests.get(value['url']).text
    body = body.encode('utf-8')
    pq = PyQuery(body)

    message('Started ' + value['name'])

    r = {
        'secret_key': constants.SECRET_KEY,
        'id': value['id'],
        'name': pq('h1').text(),
        'model': url[-1],
        'description': '',
        'description_short': pq('p[itemprop="description"]').text(),
        'price': value['price'],
        'manufacturer': value['manufacturer'],
        'category': value['category'],
        'url': url[-1],
        'weight': None,
        'weight_class_id': None,
        'height': None,
        'width': None,
        'length': None,
        'length_class_id': None,
        'product_image': [],
        'attributes': []
    }

    if not os.path.isdir(images_path):
        message('Images dir ' + images_path + ' not found. Creating')
        os.mkdir(images_path)

    image_count = 0
    for image in pq('.product-gallery__thumb.js-gallery-thumb'):
        url = pq(image).attr("data-original")

        if not url:
            continue

        response = requests.get(url)
        name = url[url.rfind("/") + 1:]
        final_path = images_path + name
        relative_name = final_path.replace(constants.RESULT_IMAGES_PATH, 'catalog/')

        if os.path.isfile(final_path):
            r['product_image'].append(relative_name)
            continue

        if processImages:
            if not os.path.isdir(images_path):
                os.mkdir(images_path)

            im = Image.open(BytesIO(response.content))
            w, h = im.size

            im.paste(watermark, (w - 150, h - 40), mask=watermark)
            im.save(final_path)
            im.close()

            r['product_image'].append(relative_name)

    if len(r['product_image']) > 0:
        r['image'] = r['product_image'][0]
        del r['product_image'][0]

    message('Images done ' + str(len(r['product_image'])))

    pq(pq('.product-specs__table tbody')[0]).replace_with('')
    pq('.product-specs__table columns').replace_with('')
    specs = pq('.product-specs__table')
    specs.find('.product-tip-wrapper').replace_with('')

    r['description'] = '<table class="product-specs">' + specs.html() + '</table>'

    for tbody in specs.find('tbody'):
        tbody = pq(tbody)
        section = tbody.find('.product-specs__table-title-inner')

        if not section:
            continue

        attribute_group = section.text()

        for tr in tbody.find('tr'):
            tr = pq(tr)

            if not tr.find('span'):
                continue

            attribute_name = pq(tr.find('td')[0]).text()
            attribute_value = pq(tr.find('td')[1]).text()

            if pq(tr.find('td')[1]).find('.i-tip'):
                attribute_value = 'Да'

            if not attribute_value:
                continue

            attribute_value_array = attribute_value.split('\xa0')

            if attribute_name == 'Вес' or attribute_name == 'Вес (с подставкой)':

                r['weight'] = float(attribute_value_array[0])

                if attribute_value_array[1] == 'кг':
                    r['weight_class_id'] = 1

                if attribute_value_array[1] == 'гр':
                    r['weight_class_id'] = 2

            if attribute_name == 'Ширина':
                r['width'] = float(attribute_value_array[0])

            if attribute_name == 'Высота' or attribute_name == 'Высота (с учетом подставки)':
                r['height'] = float(attribute_value_array[0])

            if attribute_name == 'Длина' or attribute_name == 'Глубина' or attribute_name == 'Толщина панели':
                r['length'] = float(attribute_value_array[0])

                if attribute_value_array[1] == 'см':
                    r['length_class_id'] = 1

                if attribute_value_array[1] == 'мм':
                    r['length_class_id'] = 2

            r['attributes'].append({
                'name': attribute_name,
                'value': attribute_value,
                'group': attribute_group
            })

    json_formatted = json.dumps(r, sort_keys=True, indent=4, separators=(',', ': '), ensure_ascii=False)
    f = open(result_file, "w+")
    f.write(json_formatted)
    f.close()

    message('Data file ' + result_file + ' created')

    if makeCMSRequest:
        message('Started request to CMS')

        post = requests.post(constants.CMS_API_URL,
                             headers={'Content-Type': 'application/json', 'Accept': 'application/json'},
                             json=r)

        post.encoding = 'utf-8'
        message('CMS response:' + post.text)

    message('Product ' + value['name'] + ' done')
    message('Doing pause in ' + str(constants.PAUSE) + 's...')

    time.sleep(constants.PAUSE)
