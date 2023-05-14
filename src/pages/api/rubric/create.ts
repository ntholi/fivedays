import { classroom_v1 } from 'googleapis';
import { NextApiRequest, NextApiResponse } from 'next';

type Data = {
  courseWork: classroom_v1.Schema$CourseWork;
};

export default function handler(
  req: NextApiRequest,
  res: NextApiResponse<Data>
) {
  const { courseWork } = req.body;
  console.log(courseWork);
  res.status(200).json(courseWork);
}
