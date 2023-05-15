import { deleteRubric, saveRubric } from '@/lib/services/rubricService';
import { NextApiRequest, NextApiResponse } from 'next';

export default async function handler(
  req: NextApiRequest,
  res: NextApiResponse
) {
  const { questionId, data } = req.body;

  //clear rubric first
  deleteRubric(questionId);

  data.forEach(async (rubric: Rubric) => {
    rubric.questionId = questionId;
    saveRubric(rubric);
  });

  res.status(200).json({ message: 'Rubrics saved successfully' });
}
