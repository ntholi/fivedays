import { createCompletion } from '@/lib/helpers/openai';
import { createRubricPrompt } from '@/lib/helpers/prompts/rubric';
import { NextApiRequest, NextApiResponse } from 'next';
import { Configuration, OpenAIApi } from 'openai';

export default async function handler(
  req: NextApiRequest,
  res: NextApiResponse<Rubric[]>
) {
  const { question, points } = req.body;

  const prompt = createRubricPrompt(question, points);
  const response = await createCompletion(prompt);

  console.log(response);

  res.status(200).json(JSON.parse(response.choices[0].message?.content || ''));
}
