import { NextApiRequest, NextApiResponse } from 'next';
import { createRubric } from '@/lib/services/rubricService';

export default async function handler(
  req: NextApiRequest,
  res: NextApiResponse
) {
  const { questionId, data } = req.body;

  await createRubric(questionId, data);

  res.status(200).json({ message: 'Rubrics saved successfully' });
}
