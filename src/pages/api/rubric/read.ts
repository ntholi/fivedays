import { getRubric, saveRubric } from '@/lib/services/rubricService';
import { NextApiRequest, NextApiResponse } from 'next';

export default async function handler(
  req: NextApiRequest,
  res: NextApiResponse<Rubric[]>
) {
  const { questionId } = req.query;
  const rubrics = getRubric(String(questionId));

  console.log(questionId, rubrics);

  res.status(200).json(rubrics);
}
