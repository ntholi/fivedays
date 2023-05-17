export function createGradingPrompt(
  question: string,
  answer: string,
  rubric: string
) {
  return `Grade answer to question: "${question}" with answer: "${answer}" and rubric: "${rubric}", keep the comments short
  return valid JSON in Format: [{title: string, points: number, comment: string}, {...]`;
}
