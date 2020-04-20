/**
 iZ³ | Izzzio blockchain - https://izzz.io

 Copyright 2018 Izio LLC (OOO "Изио")

 Licensed under the Apache License, Version 2.0 (the "License");
 you may not use this file except in compliance with the License.
 You may obtain a copy of the License at

 http://www.apache.org/licenses/LICENSE-2.0

 Unless required by applicable law or agreed to in writing, software
 distributed under the License is distributed on an "AS IS" BASIS,
 WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 See the License for the specific language governing permissions and
 limitations under the License.
 */

const MongoClient = require('mongodb').MongoClient;
const logger = new (require(global.PATH.mainDir + '/modules/logger'))("MongoDB");

const collectionName = 'main';

class MongoDB {
    constructor(connectionString, workDir) {
        this.connectionString = 'mongodb://' + connectionString;
        this.workDir = workDir;
        this._initialized = false;
        this.client = '';
        this.dbName = '';
    }

    _init() {
        let that = this;
        let connectParams = this.connectionString.split("/");
        this.dbName = connectParams[connectParams.length - 1];

        return new Promise((resolve, reject) => {
            MongoClient.connect(
                this.connectionString,
                {
                    useNewUrlParser: true,
                    useUnifiedTopology: true
                },
                (err, client) => {
                    if (err) {
                        logger.fatalFall('Connection error: ' + err);
                    } else {
                        logger.info('Connected correctly to DB ' + this.dbName);
                        this._initialized = true;
                        that.client = client;
                        resolve();
                    }
                });
        });
    }

    async put(key, value, options, callback) {
        if (!this._initialized) {
            await this._init();
        }
        await this.client
            .db(this.dbName)
            .collection(collectionName)
            .insertOne({[key]: value}, {}, (err, result) => {
                if (callback) {
                    callback(err);
                }
            });
    }

    async get(key, options, callback) {
        if (!this._initialized) {
            await this._init();
        }

        this.client
            .db(this.dbName)
            .collection(collectionName)
            .find({[key]: {$exists: true}}).toArray((err, result) => {

            if (!result || !result.length) {
                err = true;
            }

            if (err) {
                return callback(err);
            }

            result = result[0][key];
            return callback('', result);
        });
    }

    async del(key, options, callback) {
        if (!this._initialized) {
            await this._init();
        }

        this.client
            .db(this.dbName)
            .collection(collectionName)
            .deleteMany({[key]: {$exists: true}}, {}, (err, result) => {
                if (err || !result.result.ok) {
                    logger.warning('Delete from DB execution went with error. Key = ' + key + '. Error: ' + err);
                }
                callback();
            });
    }

    async close(callback) {
        await this.client.close(callback);
    }

    async clear(callback) {


        console.log("111");


        if (!this._initialized) {
            await this._init();
        }


        console.log("222");


        this.client
            .db(this.dbName)
            .collection(collectionName)
            .deleteMany({},{}, (err, result) => {


                console.log('ERROR = '+err);
                console.log('RESULT = '+result);


                // Ensure we don't have the collection in the set of names
                this.client.db.listCollections().toArray(function(err, replies) {

                    var found = false;
                    // For each collection in the list of collection names in this db look for the
                    // dropped collection
                    replies.forEach(function (document) {
                        if (document.name == "main") {
                            found = true;
                            return;
                        }
                    });

                    // Ensure the collection is not found
                    console.log('FOUND: '+found);
                });



                if (err) {
                    logger.warning('DB not cleared: ' + err);
                }
                if (typeof callback !== 'undefined') {
                    //callback();
                    callback(err, result);
                }
            });

        console.log('AFTER--------------------');

    }

    save(callback) {
        if (typeof callback !== 'undefined') {
            callback();
        }
    }
}

exports.init = (connectionString, workDir) => {
    return new MongoDB(connectionString, workDir);
};