import { db } from '@/lib/config/firebase';
import { doc, getDoc } from 'firebase/firestore';
import { NextApiRequest, NextApiResponse } from 'next';

export default async function handler(
  req: NextApiRequest,
  res: NextApiResponse<Rubric[]>
) {
  const { questionId } = req.query;

  let rubric: Rubric[] = [];
  const docRef = doc(db, `questions/${questionId}`);
  const docSnap = await getDoc(docRef);

  if (docSnap.exists()) {
    rubric = docSnap.data().rubric;
  }

  res.status(200).json(rubric);
}
