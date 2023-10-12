'use server';

import googleClassroom from '@/lib/config/googleClassroom';

export const createWork = async (
  courseId: string,
  title: string,
  content: string
) => {
  const classroom = await googleClassroom();

  const res = await classroom.courses.courseWork.create({
    courseId: courseId,
    requestBody: {
      courseId: courseId,
      title: title,
      description: content,
      workType: 'ASSIGNMENT',
      state: 'PUBLISHED',
      maxPoints: 100,
      associatedWithDeveloper: true,
      materials: [
        {
          link: {
            url: 'https://classroom.google.com/c/MTIwMzU2NzQyNzY4/a/MTIwMzU2NzQyNzY5/details',
          },
        },
      ],
    },
  });

  console.log({ res });
};
