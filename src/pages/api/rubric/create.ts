import { createRubricPrompt } from '@/lib/helpers/prompts/rubric';
import { classroom_v1 } from 'googleapis';
import { NextApiRequest, NextApiResponse } from 'next';
export default async function handler(
  req: NextApiRequest,
  res: NextApiResponse
) {
  const { questionId, data } = req.body;

  console.log(questionId);
  console.log(data);

  res.status(200);
}
