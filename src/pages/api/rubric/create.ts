import { saveRubric } from '@/lib/services/rubricService';
import { NextApiRequest, NextApiResponse } from 'next';

export default async function handler(
  req: NextApiRequest,
  res: NextApiResponse
) {
  const { questionId, data } = req.body;

  data.forEach((rubric: Rubric) => {
    rubric.questionId = questionId;
    saveRubric(rubric);
  });

  res.status(200);
}
