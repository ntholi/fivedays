import { NextApiRequest, NextApiResponse } from 'next';
import { getSession } from 'next-auth/react';
import googleClassroom from '@/lib/helpers/googleClassroom';
import { classroom_v1 } from 'googleapis';

export default async function handler(
  req: NextApiRequest,
  res: NextApiResponse
) {
  const session = await getSession({ req });
  if (!session) {
    return res.status(401).json({ error: 'Unauthorized' });
  }
  const { courseId, courseWorkId, studentSubmissionId, grade } = req.query;
  const classroom = googleClassroom(session);
  await updateGrade(
    classroom,
    courseId as string,
    courseWorkId as string,
    studentSubmissionId as string,
    parseInt(grade as string)
  );

  res.status(200).send('Hello World!');
}

const updateGrade = async (
  classroom: classroom_v1.Classroom,
  courseId: string,
  courseWorkId: string,
  id: string,
  draftGrade: number
) => {
  const res = await classroom.courses.courseWork.studentSubmissions.patch({
    courseId,
    courseWorkId,
    id,
    updateMask: 'draftGrade',
    requestBody: {
      draftGrade,
    },
  });

  console.log(`Updated grade: ${res.data.draftGrade}`);
};
