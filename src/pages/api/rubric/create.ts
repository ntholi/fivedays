import { createRubricPrompt } from '@/lib/helpers/prompts/rubric';
import { classroom_v1 } from 'googleapis';
import { NextApiRequest, NextApiResponse } from 'next';
import { Configuration, OpenAIApi } from 'openai';

type Data = {
  courseWork: classroom_v1.Schema$CourseWork;
};

export default async function handler(
  req: NextApiRequest,
  res: NextApiResponse<Data>
) {
  const { courseWork } = req.body;

  const configuration = new Configuration({
    organization: process.env.OPENAI_ORG_ID,
    apiKey: process.env.OPENAI_API_KEY,
  });
  const openai = new OpenAIApi(configuration);
  const response = await openai.createChatCompletion({
    model: 'gpt-3.5-turbo',
    messages: [
      {
        role: 'user',
        content: createRubricPrompt(courseWork.description, 30),
      },
    ],
  });

  console.log(response.data.choices[0].message);

  res.status(200).json(courseWork);
}
