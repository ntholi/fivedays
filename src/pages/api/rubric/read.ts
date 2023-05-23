import { db } from '@/lib/config/firebase';
import { getRubric } from '@/lib/services/rubricService';
import { doc, getDoc } from 'firebase/firestore';
import { NextApiRequest, NextApiResponse } from 'next';

export default async function handler(
  req: NextApiRequest,
  res: NextApiResponse<Rubric[]>
) {
  const { questionId } = req.query;
  const rubric = await getRubric(questionId as string);

  res.status(200).json(rubric);
}
