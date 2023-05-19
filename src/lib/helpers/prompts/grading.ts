export function createGradingPrompt(
  question: string,
  answer: string,
  rubric: string
) {
  return `Given the following question: "${question}".
And the rubric:
${rubric}

Grade this answer:

  ${answer}

return valid JSON in Format: [{title: string, points: number, comment: string}, {...]`;
}
