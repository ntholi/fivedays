import { db } from '@/lib/config/firebase';
import { doc, setDoc } from 'firebase/firestore';
import { NextApiRequest, NextApiResponse } from 'next';

export default async function handler(
  req: NextApiRequest,
  res: NextApiResponse
) {
  const { questionId, data } = req.body;

  await setDoc(doc(db, 'questions', questionId), { rubric: data });

  res.status(200).json({ message: 'Rubrics saved successfully' });
}
