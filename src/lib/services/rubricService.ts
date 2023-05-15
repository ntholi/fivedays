import db from '../config/database';

export function saveRubric(rubric: Rubric) {
  const { questionId, title, description, points } = rubric;

  const stmt = db.prepare(`
      INSERT INTO rubrics (questionId, title, description, points)
      VALUES (?, ?, ?, ?)
    `);

  const info = stmt.run(questionId, title, description, points);

  return info.lastInsertRowid;
}

export function getRubric(questionId: number) {
  const stmt = db.prepare(`
      SELECT * FROM rubrics WHERE questionId = ?
    `);

  return stmt.all(questionId);
}
