# -*- coding: utf-8 -*-
# @Author: Comzyh
# @Date:   2016-03-18 17:02:45
# @Last Modified by:   Comzyh
# @Last Modified time: 2016-03-18 17:27:07
import sqlite3


def main():
    db = sqlite3.connect('../anyonethere.db')
    db.execute("""CREATE TABLE IF NOT EXISTS [maclist] (
        [perifx] CHAR NOT NULL ON CONFLICT REPLACE UNIQUE, 
        [manufacturer] CHAR NOT NULL);""")

    maclist_csv = open('macList.csv', 'r')
    maclist_csv.readline()
    maclist = []
    for line in maclist_csv.readlines():
        perifx = line[:6].decode('UTF-8')
        manufacturer = line[7:-1].decode('UTF-8')
        maclist.append([perifx, manufacturer])
    maclist.sort(key=lambda x: x[0])
    db.executemany('INSERT OR IGNORE INTO maclist(perifx, manufacturer) VALUES(?,?)', maclist)
    db.commit()

if __name__ == '__main__':
    main()
