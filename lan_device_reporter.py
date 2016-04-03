#!/usr/bin/env python
# -*- coding: utf-8 -*-
# @Author: Comzyh
# @Date:   2015-06-09 01:00:47
# @Last Modified by:   Comzyh
# @Last Modified time: 2016-04-03 18:26:43

import re
import json
import subprocess
import multiprocessing.dummy
import sched
import time
import requests
import os
import multiprocessing.pool
import logging
import sys

config = {
    'ip_range': '192.168.1.2-254',
    'report_url': [
        'https://<your_host_here>/anyonethere.php',
    ],
    'max_report_interval': 5 * 60,
    'scan_interval': 120,
    'reporter_id': 'LabDesktop01'
}

if os.name == 'posix':
    reg_ping_result = re.compile(
        r'rtt min/avg/max/mdev = (.*)/(?P<avg_time>.*)/(.*)/(.*) ms', re.M | re.I)
    reg_arp_result = re.compile(
        r'(?P<ip>(\d{1,3}\.){3}\d{1,3}).*?(?P<MAC>([0-9a-f]{2}:){5}[0-9a-f]{2})')
elif os.name == 'nt':
    reg_ping_result = re.compile(
        r'\d+ms.*?\d+ms.*?(?P<avg_time>\d+)ms.*?$', re.M | re.I)
    reg_arp_result = re.compile(
        r'(?P<ip>(\d{1,3}\.){3}\d{1,3}).*?(?P<MAC>([0-9a-f]{2}-){5}[0-9a-f]{2})')


def get_ip_list():
    result = re.search(
        r'(?P<prefix>(\d{1,3}\.){3})(?P<start>\d{1,3})-(?P<end>\d{1,3})', config['ip_range'])
    if result is None:
        raise Exception('ip range not valid')
    result = result.groupdict()
    return [result['prefix'] + str(suffix) for suffix in range(int(result['start']), int(result['end']) + 1)]


def ping(ip):
    if os.name == 'posix':
        args = ['ping', '-c', '4', '-W', '1', '-i', '0.2', str(ip)]
    elif os.name == 'nt':
        args = ['ping', '-n', '4', '-w', '1', str(ip)]
    p_ping = subprocess.Popen(args,
                              shell=False,
                              stdout=subprocess.PIPE)
    # save ping stdout
    p_ping_out = p_ping.communicate()[0]
    if (p_ping.wait() == 0):
        # rtt min/avg/max/mdev = 22.293/22.293/22.293/0.000 ms
        result = reg_ping_result.search(p_ping_out)
        if result:
            ping_rtt = result.group('avg_time')
            return ip, ping_rtt

    return ip, False


def get_arp_table():
    if os.name == 'posix':
        args = ['arp', '-an']
    else:
        args = ['arp', '-a']
    p_arp = subprocess.Popen(args,
                             shell=False,
                             stdout=subprocess.PIPE)
    p_arp_out = p_arp.communicate()[0]
    arp_table = {}
    for result in reg_arp_result.finditer(p_arp_out):
        # arp_table.append((result.group('ip'), result.group('MAC')))
        arp_table[result.group('ip')] = result.group('MAC')
    if os.name == 'nt':
        for ip in arp_table:
            arp_table[ip] = arp_table[ip].replace('-', ':')
    return arp_table


def scan(pool, ip_list):
    """
    @return [(ip,mac,rtt),]
    """
    ping_results = []

    def ping_warp(ip):
        ping_results.append(ping(ip))
        pass
    ping_results = []
    try:
        pool.map(ping_warp, ip_list)
    except Exception:
        logging.exception("Scan Error")

    ping_results = [result for result in ping_results if result[1]]
    arp_table = get_arp_table()
    result = [(ip, arp_table[ip], rtt)
              for ip, rtt in ping_results if ip in arp_table]
    return result


def report(scan_result):
    data = {
        'type': 'report',
        'reporter': config['reporter_id'],
        'data': scan_result
    }
    json_str = json.dumps(data)
    logging.info(json_str)
    for url in config['report_url']:
        try:
            resp = requests.post(url, data=json_str, timeout=10)
            logging.info(resp)
            logging.info(resp.text)
        except Exception:
            logging.exception("Report Failed")


def main():
    logging.basicConfig(stream=sys.stdout, level=logging.INFO,
                        format='[%(asctime)s %(levelname)s] %(message)s')
    ip_list = get_ip_list()
    num_threads = 50  # Number of threads to run in the pool.
    if os.name == 'posix':
        pool = multiprocessing.dummy.Pool(num_threads)
    else:
        pool = multiprocessing.pool.ThreadPool(num_threads)

    sch = sched.scheduler(time.time, time.sleep)
    last_data = {
        'last_set': set(),
        'last_report': 0
    }

    def scan_warp():
        logging.info("scan_warp")
        scan_result = scan(pool, ip_list)
        new_set = set([(ip, mac)for ip, mac, rtt in scan_result])
        if (new_set != last_data['last_set'] or
                time.time() - last_data['last_report'] > config['max_report_interval']):
            report(scan_result)
            last_data['last_set'] = new_set
            last_data['last_report'] = time.time()
        sch.enter(config['scan_interval'], 1, scan_warp, ())
    logging.info("Let's Enter scan!")
    sch.enter(0, 1, scan_warp, ())
    sch.run()

if __name__ == '__main__':
    main()
