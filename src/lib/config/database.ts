import sqlite from 'better-sqlite3';

const db = sqlite('./database.sqlite');

db.exec(`
  CREATE TABLE IF NOT EXISTS rubrics (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    questionId TEXT,
    title TEXT,
    description TEXT,
    points INTEGER
  )
`);

export default db;
