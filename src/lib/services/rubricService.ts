import db from '../config/database';

interface RowData {
  id: number;
  questionId: string | null;
  title: string;
  description: string;
  points: number;
}

export function saveRubric(rubric: Rubric) {
  const { questionId, title, description, points } = rubric;

  const stmt = db.prepare(`
      INSERT INTO rubrics (questionId, title, description, points)
      VALUES (?, ?, ?, ?)
    `);

  const info = stmt.run(questionId, title, description, points);

  return info.lastInsertRowid;
}

export function getRubric(questionId: string): Rubric[] {
  const stmt = db.prepare(`
    SELECT * FROM rubrics WHERE questionId = ?
  `);

  const rows = stmt.all(questionId) as RowData[];

  return rows.map((row) => {
    const { id, questionId, title, description, points } = row;
    return {
      id,
      questionId,
      title,
      description,
      points,
    } as Rubric;
  });
}

export function deleteRubric(questionId: number): void {
  const stmt = db.prepare(`
    DELETE FROM rubrics WHERE questionId = ?
  `);
  stmt.run(questionId);
}
