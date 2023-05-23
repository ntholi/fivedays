import { db } from '@/lib/config/firebase';
import { doc, getDoc, setDoc } from 'firebase/firestore';

export async function createRubric(questionId: string, data: any) {
  await setDoc(doc(db, 'questions', questionId), { rubric: data });
}

export const getRubric = async (questionId: string) => {
  let rubric: Rubric[] = [];
  const docRef = doc(db, `questions/${questionId}`);
  const docSnap = await getDoc(docRef);

  if (docSnap.exists()) {
    rubric = docSnap.data().rubric;
  }

  return rubric;
};
