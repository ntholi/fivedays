export function createRubricPrompt(question: string, points: number) {
  return `Create rubric from question, with ${points} points: "${question}"
  return valid JSON in Format: [{points: int, title: string, description: string}, {...]`;
}
