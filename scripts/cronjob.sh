#!/bin/bash
#

cd /home/picurls/picurls_data/scraper

./scraper.pl digg:{popular=1}                        picurls.sn.txt | ../scripts/picurls_db_inserter.pl

./scraper.pl reddit                                  picurls.sn.txt | ../scripts/picurls_db_inserter.pl
./scraper.pl reddit:{subreddit=science}              picurls.sn.txt | ../scripts/picurls_db_inserter.pl
./scraper.pl reddit:{subreddit=programming}          picurls.sn.txt | ../scripts/picurls_db_inserter.pl

./scraper.pl flickr                                                 | ../scripts/picurls_db_inserter.pl

./scraper.pl boingboing                                             | ../scripts/picurls_db_inserter.pl

./scraper.pl wired                                                  | ../scripts/picurls_db_inserter.pl

./scraper.pl 'delicious:{popular=1;tag=graphics}'    picurls.sb.txt | ../scripts/picurls_db_inserter.pl
./scraper.pl 'delicious:{popular=1;tag=photography}' picurls.sb.txt | ../scripts/picurls_db_inserter.pl
./scraper.pl 'delicious:{popular=1;tag=photos}'      picurls.sb.txt | ../scripts/picurls_db_inserter.pl
./scraper.pl 'delicious:{popular=1;tag=photo}'       picurls.sb.txt | ../scripts/picurls_db_inserter.pl
./scraper.pl 'delicious:{popular=1;tag=image}'       picurls.sb.txt | ../scripts/picurls_db_inserter.pl
./scraper.pl 'delicious:{popular=1;tag=images}'      picurls.sb.txt | ../scripts/picurls_db_inserter.pl
./scraper.pl 'delicious:{popular=1;tag=drawing}'     picurls.sb.txt | ../scripts/picurls_db_inserter.pl

./scraper.pl stumbleupon:{tag=graphics}              picurls.sb.txt | ../scripts/picurls_db_inserter.pl
./scraper.pl stumbleupon:{tag=photography}           picurls.sb.txt | ../scripts/picurls_db_inserter.pl
./scraper.pl stumbleupon:{tag=photos}                picurls.sb.txt | ../scripts/picurls_db_inserter.pl
./scraper.pl stumbleupon:{tag=photo}                 picurls.sb.txt | ../scripts/picurls_db_inserter.pl
./scraper.pl stumbleupon:{tag=image}                 picurls.sb.txt | ../scripts/picurls_db_inserter.pl
./scraper.pl stumbleupon:{tag=images}                picurls.sb.txt | ../scripts/picurls_db_inserter.pl
./scraper.pl stumbleupon:{tag=drawing}               picurls.sb.txt | ../scripts/picurls_db_inserter.pl

./scraper.pl simpy:{tag=graphics}                    picurls.sb.txt | ../scripts/picurls_db_inserter.pl
./scraper.pl simpy:{tag=photography}                 picurls.sb.txt | ../scripts/picurls_db_inserter.pl
./scraper.pl simpy:{tag=photos}                      picurls.sb.txt | ../scripts/picurls_db_inserter.pl
./scraper.pl simpy:{tag=photo}                       picurls.sb.txt | ../scripts/picurls_db_inserter.pl
./scraper.pl simpy:{tag=image}                       picurls.sb.txt | ../scripts/picurls_db_inserter.pl
./scraper.pl simpy:{tag=images}                      picurls.sb.txt | ../scripts/picurls_db_inserter.pl
./scraper.pl simpy:{tag=drawing}                     picurls.sb.txt | ../scripts/picurls_db_inserter.pl

./scraper.pl 'furl:{topic=photos;bad_urls=digg,bluedot,netscape,propeller}'      picurls.sb.txt | ../scripts/picurls_db_inserter.pl
./scraper.pl 'furl:{topic=photography;bad_urls=digg,bluedot,netscape,propeller}' picurls.sb.txt | ../scripts/picurls_db_inserter.pl
./scraper.pl 'furl:{topic=image;bad_urls=digg,bluedot,netscape,propeller}'       picurls.sb.txt | ../scripts/picurls_db_inserter.pl
./scraper.pl 'furl:{topic=images;bad_urls=digg,bluedot,netscape,propeller}'      picurls.sb.txt | ../scripts/picurls_db_inserter.pl
./scraper.pl 'furl:{topic=graphics;bad_urls=digg,bluedot,netscape,propeller}'    picurls.sb.txt | ../scripts/picurls_db_inserter.pl
./scraper.pl 'furl:{topic=photo;bad_urls=digg,bluedot,netscape,propeller}'       picurls.sb.txt | ../scripts/picurls_db_inserter.pl
./scraper.pl 'furl:{topic=drawing;bad_urls=digg,bluedot,netscape,propeller}'     picurls.sb.txt | ../scripts/picurls_db_inserter.pl

../scripts/pic_mover.pl

